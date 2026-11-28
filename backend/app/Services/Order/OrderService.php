<?php

namespace App\Services\Order;

use App\Enums\AddressType;
use App\Enums\CartItemSource;
use App\Enums\OrderApprovalStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PriceSource;
use App\Enums\RfqStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\DocumentSequence;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderApprovalRequestedNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Services\Cart\CartService;
use App\Services\Finance\CreditLedgerService;
use App\Services\Finance\InvoiceService;
use App\Services\Inventory\InventoryService;
use App\Services\Payment\GatewayManager;
use App\Services\Pricing\PriceQuote;
use App\Services\Pricing\PricingEngineService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Order lifecycle. Every state change runs in a transaction with a fixed lock order
 * (company → order → products) and records an order_status_histories row.
 *
 * Stock is reserved (decremented) at placement and restored on cancellation, so the
 * catalog never oversells while orders await confirmation.
 */
class OrderService
{
    public function __construct(
        private readonly PricingEngineService $pricing,
        private readonly InventoryService $inventory,
        private readonly CreditLedgerService $ledger,
        private readonly InvoiceService $invoices,
        private readonly CartService $cart,
        private readonly GatewayManager $gateways,
    ) {}

    /** Buyer checkout from their cart. */
    public function checkout(User $buyer, CheckoutData $data): Order
    {
        $cart = $this->cart->cartFor($buyer);
        $lines = $cart->items()->pluck('quantity', 'product_id')->map(fn ($q) => (int) $q)->all();

        if ($lines === []) {
            throw BusinessRuleException::make('cart_empty');
        }

        $order = $this->place($buyer->company, $buyer, $lines, $data, cart: $cart);

        $this->afterPlacement($order);

        return $order;
    }

    /** Sales/Account Manager places an order on behalf of a buyer user of an assigned company. */
    public function placeOnBehalf(User $staff, Company $company, User $buyer, array $lines, CheckoutData $data): Order
    {
        if ($buyer->company_id !== $company->id || ! $buyer->canTransact()) {
            throw BusinessRuleException::make('order_buyer_invalid');
        }

        $order = $this->place($company, $buyer, $lines, $data, staff: $staff);
        $this->afterPlacement($order);

        return $order;
    }

    /** Converts an accepted (or acceptable quoted) RFQ into an order at the quoted prices. */
    public function convertRfq(User $buyer, Rfq $rfq, CheckoutData $data): Order
    {
        $order = DB::transaction(function () use ($buyer, $rfq, $data): Order {
            $rfq = Rfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            if ($rfq->status === RfqStatus::Quoted && ! $rfq->isExpired()) {
                $rfq->forceFill(['status' => RfqStatus::Accepted, 'responded_at' => now()])->save();
            }

            if ($rfq->status !== RfqStatus::Accepted || $rfq->isExpired()) {
                throw BusinessRuleException::make('rfq_not_convertible', ['status' => $rfq->status->label()]);
            }

            $items = $rfq->items()->get();
            $lines = $items->mapWithKeys(fn ($i) => [$i->product_id => $i->quantity])->all();
            $prices = $items->mapWithKeys(fn ($i) => [$i->product_id => (string) $i->quoted_unit_price])->all();

            $order = $this->place($buyer->company, $buyer, $lines, $data, rfq: $rfq, fixedPrices: $prices);

            $rfq->forceFill(['status' => RfqStatus::Converted, 'converted_order_id' => $order->id])->save();
            AuditLog::record('rfq.converted', $rfq, [], ['order' => $order->order_number]);

            return $order;
        });

        $this->afterPlacement($order);

        return $order;
    }

    /** Buyer Admin approves a sub-user's order that exceeded their approval limit. */
    public function approve(Order $order, User $approver): Order
    {
        return DB::transaction(function () use ($order, $approver): Order {
            $order = $this->lockOrder($order);

            if ($order->approval_status !== OrderApprovalStatus::Pending || $order->status !== OrderStatus::Pending) {
                throw BusinessRuleException::make('order_not_awaiting_approval');
            }

            $order->forceFill([
                'approval_status' => OrderApprovalStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ])->save();

            $this->history($order, OrderStatus::Pending, OrderStatus::Pending, $approver, __('messages.orders.history_approved', [], 'en'));

            return $order;
        });
    }

    public function rejectApproval(Order $order, User $approver, string $reason): Order
    {
        return DB::transaction(function () use ($order, $approver, $reason): Order {
            $this->ledger->lockCompany($order->company_id); // lock order: company → order
            $order = $this->lockOrder($order);

            if ($order->approval_status !== OrderApprovalStatus::Pending) {
                throw BusinessRuleException::make('order_not_awaiting_approval');
            }

            $order->forceFill(['approval_status' => OrderApprovalStatus::Rejected, 'approved_by' => $approver->id, 'approved_at' => now()])->save();

            return $this->cancelLocked($order, $approver, $reason);
        });
    }

    /** Staff confirms: issues the invoice and posts the receivable to the ledger. */
    public function confirm(Order $order, User $staff, ?string $comment = null): Order
    {
        $order = DB::transaction(function () use ($order, $staff, $comment): Order {
            $company = $this->ledger->lockCompany($order->company_id);
            $order = $this->lockOrder($order);

            $this->assertTransition($order, OrderStatus::Confirmed);

            if (in_array($order->approval_status, [OrderApprovalStatus::Pending, OrderApprovalStatus::Rejected], true)) {
                throw BusinessRuleException::make('order_awaiting_buyer_approval');
            }

            if ($order->payment_method === PaymentMethod::Gateway && $order->payment_status !== OrderPaymentStatus::Paid) {
                throw BusinessRuleException::make('order_gateway_unpaid');
            }

            $order->forceFill(['status' => OrderStatus::Confirmed, 'confirmed_at' => now()])->save();
            $this->invoices->issueForOrder($company, $order, $staff);
            $this->history($order, OrderStatus::Pending, OrderStatus::Confirmed, $staff, $comment);

            return $order;
        });

        $this->notifyStatus($order);

        return $order;
    }

    /** Generic forward transition used by staff (processing) and fulfilment (dispatch/delivery). */
    public function transition(Order $order, OrderStatus $to, ?User $actor, ?string $comment = null, bool $notify = true): Order
    {
        if ($to === OrderStatus::Cancelled) {
            return $this->cancel($order, $actor, (string) $comment);
        }

        if ($to === OrderStatus::Confirmed) {
            return $this->confirm($order, $actor, $comment);
        }

        $order = DB::transaction(function () use ($order, $to, $actor, $comment): Order {
            $order = $this->lockOrder($order);
            $from = $order->status;
            $this->assertTransition($order, $to);

            $timestamps = match ($to) {
                OrderStatus::Dispatched => ['dispatched_at' => now()],
                OrderStatus::Delivered => ['delivered_at' => now()],
                default => [],
            };

            $order->forceFill(['status' => $to] + $timestamps)->save();
            $this->history($order, $from, $to, $actor, $comment);

            return $order;
        });

        if ($notify) {
            $this->notifyStatus($order);
        }

        return $order;
    }

    /** Cancels, restores reserved stock and voids the invoice (ledger reversal). */
    public function cancel(Order $order, ?User $actor, string $reason): Order
    {
        $order = DB::transaction(function () use ($order, $actor, $reason): Order {
            $this->ledger->lockCompany($order->company_id);

            return $this->cancelLocked($this->lockOrder($order), $actor, $reason);
        });

        $this->notifyStatus($order);

        return $order;
    }

    /**
     * One-click reorder: re-validates every line against current price, MOQ and stock.
     * Valid lines go to the cart; others are reported with the reason.
     *
     * @return array{added: list<array>, skipped: list<array>}
     */
    public function reorder(Order $order, User $buyer): array
    {
        $order->loadMissing('items');
        $products = Product::query()->active()->whereIn('id', $order->items->pluck('product_id'))->with('priceTiers')->get()->keyBy('id');

        $added = $skipped = $quantities = [];

        foreach ($order->items as $item) {
            $product = $products->get($item->product_id);

            if ($product === null) {
                $skipped[] = ['sku' => $item->sku, 'name' => $item->product_name, 'reason' => 'product_unavailable', 'message' => __('messages.pricing.product_unavailable')];

                continue;
            }

            $quote = $this->pricing->quote($product, $item->quantity, $buyer->company);

            if (! $quote->isValid()) {
                $skipped[] = ['sku' => $item->sku, 'name' => $product->name, 'reason' => $quote->errors[0]['code'], 'message' => $quote->errors[0]['message']];

                continue;
            }

            $quantities[$product->id] = $item->quantity;
            $added[] = ['sku' => $product->sku, 'name' => $product->name, 'quantity' => $item->quantity, 'unit_price' => $quote->unitPrice, 'previous_unit_price' => (string) $item->unit_price];
        }

        if ($quantities !== []) {
            $this->cart->addMany($buyer, $quantities, CartItemSource::Reorder, merge: false);
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    // ------------------------------------------------------------------ internals

    /**
     * @param  array<int, int>  $lines  product_id => quantity
     * @param  array<int, string>  $fixedPrices  product_id => unit price (RFQ)
     */
    private function place(
        Company $company,
        User $placedBy,
        array $lines,
        CheckoutData $data,
        ?User $staff = null,
        ?Rfq $rfq = null,
        array $fixedPrices = [],
        ?Cart $cart = null,
    ): Order {
        return DB::transaction(function () use ($company, $placedBy, $lines, $data, $staff, $rfq, $fixedPrices, $cart): Order {
            $company = $this->ledger->lockCompany($company);

            if (! $company->isApproved()) {
                throw BusinessRuleException::make('kyc_not_approved', [], 403);
            }

            $shipping = $this->resolveShippingAddress($company, $data->shippingAddressId);
            $billing = $company->addresses()->billing()->orderByDesc('is_default')->first() ?? $shipping;

            $products = $this->inventory->lockProducts(array_keys($lines));
            $quotes = $this->priceLocked($products, $lines, $company, $fixedPrices);

            $invalid = $quotes->reject->isValid()->map(fn (PriceQuote $q) => ['product_id' => $q->productId, 'errors' => $q->errors])->values();
            $missing = array_values(array_diff(array_keys($lines), $products->filter(fn (Product $p) => $p->is_active)->keys()->all()));

            if ($missing !== [] || $invalid->isNotEmpty()) {
                throw BusinessRuleException::make('checkout_lines_invalid', [], 422, ['lines' => $invalid->all(), 'unavailable_product_ids' => $missing]);
            }

            $totals = $this->totals($quotes);
            $this->assertPaymentMethodAllowed($company, $data->paymentMethod, $totals['grand_total']);

            $needsApproval = $staff === null && $placedBy->requiresApprovalFor($totals['grand_total']);

            $order = new Order(['po_number' => $data->poNumber, 'buyer_notes' => $data->buyerNotes]);
            $order->forceFill([
                'order_number' => DocumentSequence::next('order'),
                'company_id' => $company->id,
                'placed_by' => $placedBy->id,
                'created_by_staff_id' => $staff?->id,
                'rfq_id' => $rfq?->id,
                'status' => OrderStatus::Pending,
                'approval_status' => $needsApproval ? OrderApprovalStatus::Pending : OrderApprovalStatus::NotRequired,
                'payment_method' => $data->paymentMethod,
                'payment_status' => OrderPaymentStatus::Unpaid,
                'shipping_address_id' => $shipping->id,
                'billing_address' => $billing->toSnapshot(),
                'shipping_address' => $shipping->toSnapshot(),
                'currency' => config('b2b.currency.code'),
                'locale' => $placedBy->preferredLocale(),
                'internal_notes' => $staff ? $data->internalNotes : null,
            ] + $totals)->save();

            $order->items()->createMany($quotes->map(function (PriceQuote $q) use ($products) {
                $product = $products->get($q->productId);
                $product->loadMissing('unit');

                return [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'product_name' => $product->getTranslations('name'),
                    'unit_name' => $product->unit->getTranslations('short_name'),
                    'pack_size' => $product->pack_size,
                    'quantity' => $q->quantity,
                    'list_price' => $q->listPrice,
                    'unit_price' => $q->unitPrice,
                    'price_source' => $q->source,
                    'vat_percent' => $q->vatPercent,
                    'line_subtotal' => $q->lineSubtotal,
                    'discount_amount' => $q->discountAmount,
                    'vat_amount' => $q->vatAmount,
                    'line_total' => $q->lineTotal,
                ];
            })->all());

            $this->inventory->reserve($lines);
            $this->history($order, null, OrderStatus::Pending, $staff ?? $placedBy, $staff ? __('messages.orders.history_on_behalf', ['name' => $staff->name], 'en') : null);

            $cart?->items()->delete();

            AuditLog::record('order.placed', $order, [], ['total' => $order->grand_total, 'method' => $data->paymentMethod->value]);

            return $order;
        });
    }

    /** @return Collection<int, PriceQuote> */
    private function priceLocked(Collection $products, array $lines, Company $company, array $fixedPrices): Collection
    {
        $priceable = collect($lines)->filter(fn ($qty, $id) => $products->has($id) && $products->get($id)->is_active);

        if ($fixedPrices !== []) {
            return $priceable->map(fn (int $qty, int $id) => $this->pricing->quoteAtFixedPrice($products->get($id), $qty, $fixedPrices[$id], PriceSource::Rfq))->values();
        }

        return $this->pricing->quoteMany($priceable->map(fn (int $qty, int $id) => [$products->get($id), $qty])->values(), $company);
    }

    /** @return array{subtotal: string, discount_total: string, vat_total: string, shipping_total: string, grand_total: string} */
    private function totals(Collection $quotes): array
    {
        $subtotal = Money::add('0', ...$quotes->map(fn (PriceQuote $q) => $q->lineSubtotal)->all());
        $discount = Money::add('0', ...$quotes->map(fn (PriceQuote $q) => $q->discountAmount)->all());
        $vat = Money::add('0', ...$quotes->map(fn (PriceQuote $q) => $q->vatAmount)->all());

        $minimum = (string) Setting::get('orders.min_order_amount', '0');
        if (Money::compare($subtotal, $minimum) < 0) {
            throw BusinessRuleException::make('order_below_minimum', ['amount' => $minimum]);
        }

        $threshold = (string) Setting::get('orders.free_shipping_threshold', '0');
        $shipping = Money::isPositive($threshold) && Money::compare($subtotal, $threshold) >= 0
            ? '0.00'
            : Money::format(Money::of((string) Setting::get('orders.flat_shipping_fee', '0')));

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'vat_total' => $vat,
            'shipping_total' => $shipping,
            'grand_total' => Money::add($subtotal, $vat, $shipping),
        ];
    }

    private function assertPaymentMethodAllowed(Company $lockedCompany, PaymentMethod $method, string $grandTotal): void
    {
        match ($method) {
            PaymentMethod::Credit => $this->ledger->assertCreditAvailable($lockedCompany, $grandTotal),
            PaymentMethod::Cod => Money::compare($grandTotal, (string) Setting::get('orders.cod_max_amount', '999999999')) > 0
                ? throw BusinessRuleException::make('cod_limit_exceeded', ['max' => (string) Setting::get('orders.cod_max_amount')])
                : null,
            PaymentMethod::Gateway => $this->gateways->defaultEnabled() ? null : throw BusinessRuleException::make('gateway_unavailable'),
            PaymentMethod::BankTransfer => null,
        };
    }

    private function resolveShippingAddress(Company $company, int $addressId): CompanyAddress
    {
        $address = CompanyAddress::query()
            ->where('company_id', $company->id)
            ->where('type', AddressType::Shipping->value)
            ->whereKey($addressId)
            ->first();

        if ($address === null) {
            throw BusinessRuleException::make('shipping_address_invalid');
        }

        return $address;
    }

    private function cancelLocked(Order $order, ?User $actor, string $reason): Order
    {
        if (! $order->isCancellable()) {
            throw BusinessRuleException::make('order_not_cancellable', ['status' => $order->status->label()]);
        }

        $from = $order->status;
        $order->loadMissing('items');

        $restore = [];
        foreach ($order->items as $item) {
            $restore[$item->product_id] = ($restore[$item->product_id] ?? 0) + $item->remainingQuantity();
        }
        $this->inventory->restore($restore);

        if ($invoice = $order->invoice()->lockForUpdate()->first()) {
            $this->invoices->void($this->ledger->lockCompany($order->company_id), $invoice, $reason, $actor);
        }

        $order->forceFill([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $actor?->id,
            'cancellation_reason' => $reason,
        ])->save();

        $this->invoices->syncOrderPayment($order);
        $this->history($order, $from, OrderStatus::Cancelled, $actor, $reason);
        AuditLog::record('order.cancelled', $order, ['status' => $from->value], ['reason' => $reason]);

        return $order;
    }

    private function lockOrder(Order $order): Order
    {
        return Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    private function assertTransition(Order $order, OrderStatus $to): void
    {
        if (! $order->status->canTransitionTo($to)) {
            throw BusinessRuleException::make('order_invalid_transition', [
                'from' => $order->status->label(),
                'to' => $to->label(),
            ]);
        }
    }

    private function history(Order $order, ?OrderStatus $from, OrderStatus $to, ?User $actor, ?string $comment): void
    {
        OrderStatusHistory::query()->forceCreate([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor?->id,
            'comment' => $comment,
            'created_at' => now(),
        ]);
    }

    private function afterPlacement(Order $order): void
    {
        $order->loadMissing('company');

        $staff = $order->company->account_manager_id
            ? User::query()->whereKey($order->company->account_manager_id)->active()->get()
            : User::query()->staff()->active()->role(Role::SuperAdmin->value)->get();

        Notification::send($staff, new OrderPlacedNotification($order));

        if ($order->approval_status === OrderApprovalStatus::Pending) {
            $approvers = $order->company->users()->active()->role(Role::BuyerAdmin->value)->get();
            Notification::send($approvers, new OrderApprovalRequestedNotification($order));
        }
    }

    private function notifyStatus(Order $order): void
    {
        $recipient = User::query()->whereKey($order->placed_by)->active()->first();

        if ($recipient !== null) {
            $recipient->notify(new OrderStatusChangedNotification($order));
        }
    }
}
