<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\PriceQuoteRequest;
use App\Http\Requests\Catalog\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Services\Catalog\CatalogCache;
use App\Services\Pricing\PricingEngineService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Catalog browsing for any authenticated user. Prices are attached (and emitted) only when the
 * viewer may see them; pending/rejected buyers get the catalog without wholesale prices.
 */
class CatalogController extends Controller
{
    public function __construct(private readonly PricingEngineService $pricing) {}

    public function categories(): JsonResponse
    {
        return response()->json(['data' => CatalogCache::categories()]);
    }

    public function brands(): JsonResponse
    {
        return response()->json(['data' => CatalogCache::brands()]);
    }

    public function index(ProductIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $user = $request->user();

        $query = Product::query()
            ->active()
            ->with(['unit', 'brand:id,name', 'category:id,name,slug'])
            ->search($filters['q'] ?? null)
            ->when($filters['category_id'] ?? null, fn (Builder $q, int $id) => $q->whereIn('category_id', $this->categoryWithChildren($id)))
            ->when($filters['brand_ids'] ?? null, fn (Builder $q, array $ids) => $q->whereIn('brand_id', $ids))
            ->when($request->boolean('in_stock'), fn (Builder $q) => $q->where('stock_quantity', '>', 0))
            ->when($request->boolean('featured'), fn (Builder $q) => $q->where('is_featured', true));

        $this->applySort($query, $filters['sort'] ?? (empty($filters['q']) ? 'name' : 'relevance'));

        $products = $query->paginate((int) ($filters['per_page'] ?? 24))->withQueryString();

        if ($user->canSeePrices()) {
            $this->attachPricing($products->getCollection(), $user->company);
        }

        return ProductResource::collection($products);
    }

    public function show(Request $request, string $slug): ProductResource
    {
        $product = Product::query()
            ->active()
            ->where('slug', $slug)
            ->with(['unit', 'brand:id,name', 'category:id,name,slug', 'images'])
            ->firstOrFail();

        if ($request->user()->canSeePrices()) {
            $this->attachPricing(new Collection([$product]), $request->user()->company);
        }

        return new ProductResource($product);
    }

    /** Lightweight typeahead for the Quick Order Pad (SKU / barcode / EN+BN name). */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:64']]);
        $showStock = $request->user()->canSeePrices();

        $results = Product::query()
            ->active()
            ->search($validated['q'])
            ->with('unit:id,code,short_name')
            ->orderByRaw('CASE WHEN sku = ? THEN 0 ELSE 1 END', [strtoupper($validated['q'])])
            ->orderBy('sku')
            ->limit(15)
            ->get(['id', 'sku', 'name', 'unit_id', 'pack_size', 'moq', 'step_quantity', 'max_order_quantity', 'stock_quantity', 'low_stock_threshold', 'is_active', 'base_price'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'unit' => $p->unit->short_name,
                'pack_size' => $p->pack_size,
                'moq' => $p->moq,
                'step_quantity' => $p->step_quantity,
                'max_order_quantity' => $p->max_order_quantity,
                'stock_status' => $p->stockStatus(),
                'available_quantity' => $showStock ? $p->stock_quantity : null,
                'list_price' => $showStock ? $p->base_price : null,
            ]);

        return response()->json(['data' => $results]);
    }

    /** Batch live price recalculation (tier selector / steppers). Requires an approved company. */
    public function quote(PriceQuoteRequest $request): JsonResponse
    {
        $lines = collect($request->validated('lines'));
        $products = Product::query()->active()->whereIn('id', $lines->pluck('product_id'))->with('priceTiers')->get()->keyBy('id');

        $priceable = $lines->filter(fn (array $l) => $products->has($l['product_id']))->values();
        $quotes = $this->pricing->quoteMany(
            $priceable->map(fn (array $l) => [$products->get($l['product_id']), (int) $l['quantity']]),
            $request->user()->company,
        );

        return response()->json([
            'data' => $quotes->map->toArray()->values(),
            'unavailable_product_ids' => $lines->pluck('product_id')->diff($products->keys())->values(),
        ]);
    }

    private function attachPricing(Collection $products, ?Company $company): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $products->loadMissing('priceTiers');
        $quotes = $this->pricing
            ->quoteMany($products->map(fn (Product $p) => [$p, max(1, $p->moq)]), $company, checkStock: false)
            ->keyBy('productId');
        $tables = $this->pricing->tierTables($products, $company);

        foreach ($products as $product) {
            $product->setRelation('pricing', [
                'quote' => $quotes->get($product->id),
                'tier_table' => $tables[$product->id] ?? [],
            ]);
        }
    }

    /** @return list<int> */
    private function categoryWithChildren(int $id): array
    {
        $ids = [$id];
        $frontier = [$id];

        // Bounded breadth-first walk (category trees are shallow).
        for ($depth = 0; $depth < 5 && $frontier !== []; $depth++) {
            $frontier = Category::query()->active()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return array_values(array_unique($ids));
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'newest' => $query->latest('id'),
            'sku' => $query->orderBy('sku'),
            'price_asc' => $query->orderBy('base_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('base_price')->orderBy('id'),
            'stock' => $query->orderByDesc('stock_quantity')->orderBy('id'),
            'relevance' => $query->orderByDesc('is_featured')->orderBy('sku'),
            default => $query->orderBy('name->'.app()->getLocale())->orderBy('id'),
        };
    }
}
