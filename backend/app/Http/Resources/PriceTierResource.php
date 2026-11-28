<?php

namespace App\Http\Resources;

use App\Models\ProductPriceTier;
use Illuminate\Http\Request;

/** @mixin ProductPriceTier */
class PriceTierResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_group_id' => $this->customer_group_id,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'unit_price' => $this->unit_price,
        ];
    }
}
