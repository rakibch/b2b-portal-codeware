<?php

namespace App\Services\Pricing;

use App\Enums\PriceSource;
use App\Models\Company;
use App\Models\CompanyProductPrice;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Single source of truth for B2B prices and order-quantity rules.
 *
 * Candidate unit prices for a quantity (the LOWEST applicable wins):
 *   - base_price less the customer group's discount_percent
 *   - general volume tier (customer_group_id NULL) less the group discount
 *   - customer-group-specific tier (absolute price)
 *   - company contract price (when quantity >= its min_quantity and within validity window)
 * RFQ quoted prices bypass the engine and are applied on RFQ → order conversion.
 *
 * Performance: quoteMany() prices any number of lines with at most 2 queries
 * (tiers eager-load + contract prices), never N+1.
 */
class PricingEngineService
{
    /** Price a single line. */
    public function quote(Product $product, int $quantity, ?Company $company, bool $checkStock = true): PriceQuote
    {
        return $this->quoteMany([[$product, $quantity]], $company, $checkStock)->first();
    }

    /**
     * @param  iterable<array{0: Product, 1: int}>  $lines
     * @return Collection<int, PriceQuote> keyed in input order
     */
    public function quoteMany(iterable $lines, ?Company $company, bool $checkStock = true): Collection
    {
        $lines = collect($lines)->values();

        if ($lines->isEmpty()) {
            return collect();
        }

        $context = $this->context($lines->map(fn (array $line) => $line[0]), $company);

        return $lines->map(fn (array $line) => $this->price(
            $line[0],
            (int) $line[1],
            $context['group'],
            $context['contracts']->get($line[0]->id),
            $checkStock,
        ));
    }

    /**
     * Effective price bands a given company sees for a product, for the catalog tier table.
     *
     * @return list<array{min_quantity: int, max_quantity: int|null, unit_price: string, price_source: string}>
     */
    public function tierTable(Product $product, ?Company $company): array
    {
        return $this->tierTables(new EloquentCollection([$product]), $company)[$product->id] ?? [];
    }

    /**
     * Batch variant of tierTable() for listings.
     *
     * @return array<int, list<array>> keyed by product id
     */
    public function tierTables(Collection $products, ?Company $company): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $context = $this->context($products, $company);
        $tables = [];

        foreach ($products as $product) {
            $tables[$product->id] = $this->buildTierTable($product, $context['group'], $context['contracts']->get($product->id));
        }

        return $tables;
    }

    /**
     * Quantity rule violations for a line. Stock check optional (RFQs are priced regardless of stock).
     *
     * @return list<array{code: string, message: string, meta: array}>
     */
    public function validateQuantity(Product $product, int $quantity, bool $checkStock = true): array
    {
        $errors = [];

        if (! $product->is_active || $product->trashed()) {
            return [$this->error('product_unavailable')];
        }

        $moq = max(1, $product->moq);
        $step = max(1, $product->step_quantity);

        if ($quantity < $moq) {
            $errors[] = $this->error('below_moq', ['moq' => $moq], ['suggested_quantity' => $moq]);
        } elseif (($quantity - $moq) % $step !== 0) {
            $errors[] = $this->error('invalid_step', ['step' => $step, 'moq' => $moq], [
                'suggested_quantity' => $this->suggestQuantity($product, $quantity),
            ]);
        }

        if ($product->max_order_quantity !== null && $quantity > $product->max_order_quantity) {
            $errors[] = $this->error('above_max', ['max' => $product->max_order_quantity], [
                'suggested_quantity' => $product->max_order_quantity,
            ]);
        }

        if ($checkStock && $quantity > $product->stock_quantity) {
            $errors[] = $this->error('insufficient_stock', ['available' => $product->stock_quantity], [
                'available' => $product->stock_quantity,
            ]);
        }

        return $errors;
    }

    /** Nearest valid quantity at or above the requested one (respecting MOQ, step and max). */
    public function suggestQuantity(Product $product, int $quantity): int
    {
        $moq = max(1, $product->moq);
        $step = max(1, $product->step_quantity);

        $suggested = $quantity <= $moq ? $moq : $moq + (int) ceil(($quantity - $moq) / $step) * $step;

        if ($product->max_order_quantity !== null && $suggested > $product->max_order_quantity) {
            // Largest valid quantity not exceeding max.
            $suggested = $moq + intdiv(max(0, $product->max_order_quantity - $moq), $step) * $step;
        }

        return $suggested;
    }

    /** @return array{group: ?CustomerGroup, contracts: Collection<int, CompanyProductPrice>} */
    private function context(Collection $products, ?Company $company): array
    {
        $products = $products instanceof EloquentCollection ? $products : new EloquentCollection($products->all());
        $products->loadMissing('priceTiers');

        $group = null;
        $contracts = collect();

        if ($company !== null) {
            $company->loadMissing('customerGroup');
            $group = $company->customerGroup?->is_active ? $company->customerGroup : null;

            $contracts = CompanyProductPrice::query()
                ->where('company_id', $company->id)
                ->whereIn('product_id', $products->pluck('id')->unique()->all())
                ->currentlyValid()
                ->get()
                ->keyBy('product_id');
        }

        return ['group' => $group, 'contracts' => $contracts];
    }

    private function price(
        Product $product,
        int $quantity,
        ?CustomerGroup $group,
        ?CompanyProductPrice $contract,
        bool $checkStock,
    ): PriceQuote {
        $quantity = max(0, $quantity);
        [$unitPrice, $source] = $this->resolveUnitPrice($product, $quantity, $group, $contract);

        $listPrice = Money::format(Money::of((string) $product->base_price));
        $lineSubtotal = Money::mul($unitPrice, $quantity);
        $discount = Money::max(Money::sub(Money::mul($listPrice, $quantity), $lineSubtotal), '0');
        $vatPercent = Money::format(Money::of((string) $product->vat_percent));
        $vatAmount = Money::percent($lineSubtotal, $vatPercent);

        return new PriceQuote(
            productId: $product->id,
            quantity: $quantity,
            listPrice: $listPrice,
            unitPrice: $unitPrice,
            source: $source,
            vatPercent: $vatPercent,
            lineSubtotal: $lineSubtotal,
            discountAmount: $discount,
            vatAmount: $vatAmount,
            lineTotal: Money::add($lineSubtotal, $vatAmount),
            availableStock: (int) $product->stock_quantity,
            errors: $this->validateQuantity($product, $quantity, $checkStock),
            nextTier: $this->nextTier($product, $quantity, $unitPrice, $group, $contract),
        );
    }

    /** @return array{0: string, 1: PriceSource} */
    private function resolveUnitPrice(Product $product, int $quantity, ?CustomerGroup $group, ?CompanyProductPrice $contract): array
    {
        $discountPercent = $group !== null ? (string) $group->discount_percent : '0';
        $hasGroupDiscount = Money::isPositive($discountPercent);

        $candidates = [[
            $this->applyDiscount((string) $product->base_price, $discountPercent),
            $hasGroupDiscount ? PriceSource::Group : PriceSource::Base,
        ]];

        $generalTier = $this->matchTier($product->priceTiers, null, $quantity);
        if ($generalTier !== null) {
            $candidates[] = [$this->applyDiscount((string) $generalTier->unit_price, $discountPercent), PriceSource::Tier];
        }

        if ($group !== null) {
            $groupTier = $this->matchTier($product->priceTiers, $group->id, $quantity);
            if ($groupTier !== null) {
                $candidates[] = [Money::format(Money::of((string) $groupTier->unit_price)), PriceSource::Group];
            }
        }

        if ($contract !== null && $quantity >= $contract->min_quantity) {
            $candidates[] = [Money::format(Money::of((string) $contract->unit_price)), PriceSource::Contract];
        }

        // Lowest price wins; on ties the more specific (later) candidate wins.
        $best = $candidates[0];
        foreach ($candidates as $candidate) {
            if (Money::compare($candidate[0], $best[0]) <= 0) {
                $best = $candidate;
            }
        }

        return $best;
    }

    private function matchTier(Collection $tiers, ?int $groupId, int $quantity): ?ProductPriceTier
    {
        return $tiers
            ->filter(fn (ProductPriceTier $tier) => $tier->customer_group_id === $groupId && $tier->appliesTo($quantity))
            ->sortByDesc('min_quantity')
            ->first();
    }

    private function applyDiscount(string $price, string $discountPercent): string
    {
        if (! Money::isPositive($discountPercent)) {
            return Money::format(Money::of($price));
        }

        return Money::sub($price, Money::percent($price, $discountPercent));
    }

    /** Quantity breakpoints at which this buyer's price changes. */
    private function breakpoints(Product $product, ?CustomerGroup $group, ?CompanyProductPrice $contract): array
    {
        $points = [max(1, $product->moq)];

        foreach ($product->priceTiers as $tier) {
            if ($tier->customer_group_id === null || $tier->customer_group_id === $group?->id) {
                $points[] = $tier->min_quantity;
                if ($tier->max_quantity !== null) {
                    $points[] = $tier->max_quantity + 1;
                }
            }
        }

        if ($contract !== null) {
            $points[] = max(1, $contract->min_quantity);
        }

        $points = array_values(array_unique(array_filter($points, fn (int $p) => $p >= max(1, $product->moq))));
        sort($points);

        return $points;
    }

    private function buildTierTable(Product $product, ?CustomerGroup $group, ?CompanyProductPrice $contract): array
    {
        $bands = [];

        foreach ($this->breakpoints($product, $group, $contract) as $point) {
            [$price, $source] = $this->resolveUnitPrice($product, $point, $group, $contract);
            $last = $bands === [] ? null : $bands[array_key_last($bands)];

            if ($last !== null && $last['unit_price'] === $price) {
                continue; // merge bands with identical price
            }

            if ($last !== null) {
                $bands[array_key_last($bands)]['max_quantity'] = $point - 1;
            }

            $bands[] = [
                'min_quantity' => $point,
                'max_quantity' => null,
                'unit_price' => $price,
                'price_source' => $source->value,
            ];
        }

        if ($product->max_order_quantity !== null && $bands !== []) {
            $bands[array_key_last($bands)]['max_quantity'] = $product->max_order_quantity;
        }

        return $bands;
    }

    /** "Buy N more to unlock ৳X" hint: the next valid quantity with a strictly lower unit price. */
    private function nextTier(Product $product, int $quantity, string $currentPrice, ?CustomerGroup $group, ?CompanyProductPrice $contract): ?array
    {
        foreach ($this->breakpoints($product, $group, $contract) as $point) {
            if ($point <= $quantity) {
                continue;
            }

            $target = $this->suggestQuantity($product, $point);

            if ($target <= $quantity) {
                continue;
            }

            [$price] = $this->resolveUnitPrice($product, $target, $group, $contract);

            if (Money::compare($price, $currentPrice) < 0) {
                return [
                    'min_quantity' => $target,
                    'unit_price' => $price,
                    'additional_quantity' => $target - $quantity,
                ];
            }
        }

        return null;
    }

    private function error(string $code, array $replace = [], array $meta = []): array
    {
        return [
            'code' => $code,
            'message' => __('messages.pricing.'.$code, $replace),
            'meta' => $meta,
        ];
    }
}
