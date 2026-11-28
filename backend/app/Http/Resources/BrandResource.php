<?php

namespace App\Http\Resources;

use App\Models\Brand;
use Illuminate\Http\Request;

/** @mixin Brand */
class BrandResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->storageUrl($this->logo_path),
            'is_active' => $this->is_active,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
