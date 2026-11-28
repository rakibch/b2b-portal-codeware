<?php

namespace App\Http\Resources;

use App\Models\CompanyProductPrice;
use Illuminate\Http\Request;

/** @mixin CompanyProductPrice */
class CompanyPriceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn () => ['id' => $this->product->id, 'sku' => $this->product->sku, 'name' => $this->product->name, 'base_price' => $this->product->base_price]),
            'unit_price' => $this->unit_price,
            'min_quantity' => $this->min_quantity,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
