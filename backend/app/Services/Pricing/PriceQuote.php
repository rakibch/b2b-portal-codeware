<?php

namespace App\Services\Pricing;

use App\Enums\PriceSource;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** Immutable result of pricing one product line. All money values are 2dp strings. */
final readonly class PriceQuote implements Arrayable, JsonSerializable
{
    /**
     * @param  list<array{code: string, message: string, meta: array}>  $errors
     * @param  array{min_quantity: int, unit_price: string, additional_quantity: int}|null  $nextTier
     */
    public function __construct(
        public int $productId,
        public int $quantity,
        public string $listPrice,
        public string $unitPrice,
        public PriceSource $source,
        public string $vatPercent,
        public string $lineSubtotal,
        public string $discountAmount,
        public string $vatAmount,
        public string $lineTotal,
        public int $availableStock,
        public array $errors = [],
        public ?array $nextTier = null,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function hasError(string $code): bool
    {
        foreach ($this->errors as $error) {
            if ($error['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'list_price' => $this->listPrice,
            'unit_price' => $this->unitPrice,
            'price_source' => $this->source->value,
            'price_source_label' => $this->source->label(),
            'vat_percent' => $this->vatPercent,
            'line_subtotal' => $this->lineSubtotal,
            'discount_amount' => $this->discountAmount,
            'vat_amount' => $this->vatAmount,
            'line_total' => $this->lineTotal,
            'available_stock' => $this->availableStock,
            'is_valid' => $this->isValid(),
            'errors' => $this->errors,
            'next_tier' => $this->nextTier,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
