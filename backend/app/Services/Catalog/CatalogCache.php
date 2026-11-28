<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/** Cached, locale-specific lookup lists for the catalog sidebar (busted on any change). */
class CatalogCache
{
    private const TTL_SECONDS = 3600;

    public static function categories(): array
    {
        return Cache::remember('catalog.categories.'.app()->getLocale(), self::TTL_SECONDS, function (): array {
            return Category::query()
                ->active()
                ->withCount(['products' => fn ($q) => $q->active()])
                ->orderBy('sort_order')
                ->get(['id', 'parent_id', 'name', 'slug', 'image_path', 'sort_order'])
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'parent_id' => $c->parent_id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'image_url' => $c->image_path ? asset('storage/'.$c->image_path) : null,
                    'products_count' => $c->products_count,
                ])
                ->all();
        });
    }

    public static function brands(): array
    {
        return Cache::remember('catalog.brands', self::TTL_SECONDS, function (): array {
            return Brand::query()
                ->active()
                ->withCount(['products' => fn ($q) => $q->active()])
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'logo_path'])
                ->map(fn (Brand $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'logo_url' => $b->logo_path ? asset('storage/'.$b->logo_path) : null,
                    'products_count' => $b->products_count,
                ])
                ->all();
        });
    }

    public static function flush(): void
    {
        foreach (config('b2b.supported_locales') as $locale) {
            Cache::forget('catalog.categories.'.$locale);
        }

        Cache::forget('catalog.brands');
    }
}
