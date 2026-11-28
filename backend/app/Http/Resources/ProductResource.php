<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Prices (list price, live quote, tier table, exact stock) are emitted ONLY for staff and
 * buyers of KYC-approved companies. Controllers attach computed pricing as the 'pricing'
 * relation ({quote, tier_table}) so listings are priced in one batch.
 *
 * @mixin Product
 */
class ProductResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $staff = $this->viewerIsStaff($request);
        $prices = $this->viewerCanSeePrices($request);
        $pricing = $this->relationLoaded('pricing') ? $this->getRelation('pricing') : null;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->storageUrl($this->image_path),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug] : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? ['id' => $this->brand->id, 'name' => $this->brand->name] : null),
            'unit' => $this->whenLoaded('unit', fn () => ['code' => $this->unit->code, 'name' => $this->unit->name, 'short_name' => $this->unit->short_name]),
            'pack_size' => $this->pack_size,
            'pack_label' => $this->pack_label,
            'moq' => $this->moq,
            'step_quantity' => $this->step_quantity,
            'max_order_quantity' => $this->max_order_quantity,
            'vat_percent' => $this->vat_percent,
            'stock_status' => $this->stockStatus(),
            'is_featured' => $this->is_featured,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($i) => ['url' => $this->storageUrl($i->path), 'alt' => $i->alt_text])),

            'prices_visible' => $prices,
            'list_price' => $this->when($prices, $this->base_price),
            'available_quantity' => $this->when($prices, $this->stock_quantity),
            'pricing' => $this->when($prices && $pricing !== null, fn () => [
                'quote' => $pricing['quote']?->toArray(),
                'tier_table' => $pricing['tier_table'] ?? [],
            ]),

            // Admin-only fields
            'is_active' => $this->when($staff, $this->is_active),
            'low_stock_threshold' => $this->when($staff, $this->low_stock_threshold),
            'tiers' => $this->when($staff && $this->relationLoaded('priceTiers'), fn () => PriceTierResource::collection($this->priceTiers)),
            'translations' => $this->when($staff, fn () => $this->getAllTranslations()),
            'created_at' => $this->when($staff, fn () => $this->created_at?->toIso8601String()),
            'updated_at' => $this->when($staff, fn () => $this->updated_at?->toIso8601String()),
        ];
    }
}
