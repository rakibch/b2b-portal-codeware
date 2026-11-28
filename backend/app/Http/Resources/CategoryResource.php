<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;

/** @mixin Category */
class CategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'image_url' => $this->storageUrl($this->image_path),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'products_count' => $this->whenCounted('products'),
            'translations' => $this->when($this->viewerIsStaff($request), fn () => $this->getAllTranslations()),
        ];
    }
}
