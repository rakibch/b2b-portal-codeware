<?php

namespace App\Services\Cart;

use App\Enums\CartItemSource;
use App\Exceptions\BusinessRuleException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Pricing\PricingEngineService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(private readonly PricingEngineService $pricing) {}

    public function cartFor(User $user): Cart
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id], ['company_id' => $user->company_id]);

        // A user moved to another company must never see the previous company's cart.
        if ($cart->company_id !== $user->company_id) {
            DB::transaction(function () use ($cart, $user): void {
                $cart->items()->delete();
                $cart->forceFill(['company_id' => $user->company_id])->save();
            });
        }

        return $cart;
    }

    /**
     * Adds/sets many lines atomically. All lines are validated (MOQ, step, max, stock) against the
     * resulting quantity before anything is written; any invalid line aborts the whole operation.
     *
     * @param  array<int, int>  $quantities  product_id => quantity
     * @param  bool  $merge  true = add to existing quantity, false = replace it
     */
    public function addMany(User $user, array $quantities, CartItemSource $source, bool $merge = true): Cart
    {
        $quantities = array_filter($quantities, fn (int $qty) => $qty > 0);

        if ($quantities === []) {
            throw BusinessRuleException::make('cart_empty_request');
        }

        $cart = $this->cartFor($user);

        return DB::transaction(function () use ($cart, $user, $quantities, $source, $merge): Cart {
            // Lock the cart row to serialise concurrent adds from the same user (double clicks, tabs).
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            $existing = $cart->items()->whereIn('product_id', array_keys($quantities))->get()->keyBy('product_id');
            $products = Product::query()->active()->whereIn('id', array_keys($quantities))->get()->keyBy('id');

            $missing = array_diff(array_keys($quantities), $products->keys()->all());
            if ($missing !== []) {
                throw BusinessRuleException::make('product_unavailable', [], 422, ['product_ids' => array_values($missing)]);
            }

            $lines = [];
            foreach ($quantities as $productId => $qty) {
                $final = $merge ? $qty + (int) ($existing->get($productId)?->quantity ?? 0) : $qty;
                $lines[$productId] = [$products->get($productId), $final];
            }

            $quotes = $this->pricing->quoteMany(array_values($lines), $user->company);
            $invalid = $quotes->reject->isValid()->map(fn ($q) => ['product_id' => $q->productId, 'errors' => $q->errors])->values();

            if ($invalid->isNotEmpty()) {
                throw BusinessRuleException::make('cart_lines_invalid', [], 422, ['lines' => $invalid->all()]);
            }

            $now = now();
            CartItem::query()->upsert(
                collect($lines)->map(fn (array $line, int $productId) => [
                    'cart_id' => $cart->id,
                    'product_id' => $productId,
                    'quantity' => $line[1],
                    'source' => $source->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all(),
                ['cart_id', 'product_id'],
                ['quantity', 'source', 'updated_at'],
            );

            $cart->touch();

            return $cart;
        });
    }

    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        $item->loadMissing(['product', 'cart.user.company']);

        if ($item->product === null) {
            throw BusinessRuleException::make('product_unavailable');
        }

        $quote = $this->pricing->quote($item->product, $quantity, $item->cart->user->company);

        if (! $quote->isValid()) {
            throw BusinessRuleException::make('cart_lines_invalid', [], 422, [
                'lines' => [['product_id' => $item->product_id, 'errors' => $quote->errors]],
            ]);
        }

        $item->update(['quantity' => $quantity]);

        return $item;
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(User $user): void
    {
        $this->cartFor($user)->items()->delete();
    }

    /**
     * Fully priced cart view. Re-validated on every read so price/stock changes surface immediately.
     *
     * @return array{items: list<array>, totals: array, has_errors: bool, item_count: int, updated_at: ?string}
     */
    public function summary(User $user): array
    {
        $cart = $this->cartFor($user);
        $items = $cart->items()
            ->with(['product' => fn ($q) => $q->withTrashed()->with(['unit', 'brand:id,name', 'priceTiers'])])
            ->orderBy('id')
            ->get();

        $priceable = $items->filter(fn (CartItem $i) => $i->product !== null);
        $quotes = $this->pricing
            ->quoteMany($priceable->map(fn (CartItem $i) => [$i->product, $i->quantity])->values(), $user->company)
            ->keyBy('productId');

        $lines = [];
        $subtotal = $discount = $vat = '0.00';
        $hasErrors = false;

        foreach ($items as $item) {
            $quote = $item->product ? $quotes->get($item->product_id) : null;
            $hasErrors = $hasErrors || $quote === null || ! $quote->isValid();

            if ($quote !== null) {
                $subtotal = Money::add($subtotal, $quote->lineSubtotal);
                $discount = Money::add($discount, $quote->discountAmount);
                $vat = Money::add($vat, $quote->vatAmount);
            }

            $lines[] = ['item' => $item, 'quote' => $quote];
        }

        return [
            'items' => $lines,
            'totals' => [
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'vat_total' => $vat,
                'grand_total' => Money::add($subtotal, $vat),
            ],
            'has_errors' => $hasErrors,
            'item_count' => $items->count(),
            'updated_at' => $cart->updated_at?->toIso8601String(),
        ];
    }
}
