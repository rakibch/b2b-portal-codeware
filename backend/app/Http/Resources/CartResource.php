<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Services\Pricing\PriceQuote;
use Illuminate\Http\Request;

/** Wraps CartService::summary() output. */
class CartResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'items' => array_map(function (array $line): array {
                /** @var CartItem $item */
                $item = $line['item'];
                /** @var PriceQuote|null $quote */
                $quote = $line['quote'];
                $product = $item->product;
                $available = $product !== null && $product->is_active && ! $product->trashed();

                return [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'source' => $this->enum($item->source),
                    'available' => $available,
                    'product' => $product ? [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'slug' => $product->slug,
                        'name' => $product->name,
                        'image_url' => $this->storageUrl($product->image_path),
                        'brand' => $product->brand?->name,
                        'unit' => ['code' => $product->unit->code, 'short_name' => $product->unit->short_name],
                        'pack_size' => $product->pack_size,
                        'pack_label' => $product->pack_label,
                        'moq' => $product->moq,
                        'step_quantity' => $product->step_quantity,
                        'max_order_quantity' => $product->max_order_quantity,
                        'stock_status' => $product->stockStatus(),
                    ] : null,
                    'pricing' => $quote?->toArray(),
                ];
            }, $summary['items']),
            'totals' => $summary['totals'],
            'item_count' => $summary['item_count'],
            'has_errors' => $summary['has_errors'],
            'currency' => config('b2b.currency.code'),
            'updated_at' => $summary['updated_at'],
        ];
    }
}
