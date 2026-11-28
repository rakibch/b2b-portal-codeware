<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustStockRequest;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Admin\SyncTiersRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Product & tiered pricing manager (Super Admin; warehouse may read + adjust stock). */
class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'trashed'])],
            'low_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['sku', 'newest', 'stock_asc', 'stock_desc', 'price_asc', 'price_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Product::query()
            ->with(['unit', 'brand:id,name', 'category:id,name,slug', 'priceTiers'])
            ->search($validated['q'] ?? null)
            ->when($validated['category_id'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id))
            ->when($validated['brand_id'] ?? null, fn (Builder $q, $id) => $q->where('brand_id', $id))
            ->when($request->boolean('low_stock'), fn (Builder $q) => $q->lowStock());

        match ($validated['status'] ?? null) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'trashed' => $query->onlyTrashed(),
            default => null,
        };

        match ($validated['sort'] ?? 'sku') {
            'newest' => $query->latest('id'),
            'stock_asc' => $query->orderBy('stock_quantity'),
            'stock_desc' => $query->orderByDesc('stock_quantity'),
            'price_asc' => $query->orderBy('base_price'),
            'price_desc' => $query->orderByDesc('base_price'),
            default => $query->orderBy('sku'),
        };

        return ProductResource::collection($query->paginate((int) ($validated['per_page'] ?? 25)));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->validated() + ['image' => $request->file('image')], $request->user());

        return (new ProductResource($this->fresh($product)))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($this->fresh($product));
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $product = $this->products->update($product, $request->validated() + ['image' => $request->file('image')], $request->user());

        return new ProductResource($this->fresh($product));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->products->delete($product, $request->user());

        return response()->json(['message' => __('messages.common.deleted')]);
    }

    public function restore(int $id): ProductResource
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $product->restore();

        return new ProductResource($this->fresh($product));
    }

    public function syncTiers(SyncTiersRequest $request, Product $product): ProductResource
    {
        $this->products->syncTiers($product, $request->validated('tiers'));

        return new ProductResource($this->fresh($product));
    }

    public function adjustStock(AdjustStockRequest $request, Product $product): ProductResource
    {
        $product = $this->products->adjustStock($product, (int) $request->validated('delta'), $request->validated('reason'), $request->user());

        return (new ProductResource($this->fresh($product)))->additional(['message' => __('messages.inventory.adjusted')]);
    }

    private function fresh(Product $product): Product
    {
        return $product->load(['unit', 'brand:id,name', 'category:id,name,slug', 'priceTiers', 'images']);
    }
}
