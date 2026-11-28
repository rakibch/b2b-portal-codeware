<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Wraps QuickOrderService::resolve() output (matched + unmatched preview). */
class QuickOrderResultResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $result = $this->resource;

        return [
            'matched' => array_map(fn (array $m) => [
                'rows' => $m['rows'],
                'product' => [
                    'id' => $m['product']->id,
                    'sku' => $m['product']->sku,
                    'name' => $m['product']->name,
                    'unit' => $m['product']->unit->short_name,
                    'pack_size' => $m['product']->pack_size,
                    'moq' => $m['product']->moq,
                    'step_quantity' => $m['product']->step_quantity,
                    'stock_status' => $m['product']->stockStatus(),
                ],
                'quantity' => $m['quote']->quantity,
                'pricing' => $m['quote']->toArray(),
            ], $result['matched']),
            'unmatched' => $result['unmatched'],
            'summary' => $result['summary'],
        ];
    }
}
