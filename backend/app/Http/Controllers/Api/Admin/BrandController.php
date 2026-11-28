<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection(Brand::query()->withCount('products')->orderBy('name')->get());
    }

    public function store(BrandRequest $request): JsonResponse
    {
        $data = $request->safe()->except('logo');
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']);
        $data['logo_path'] = $this->storeLogo($request->file('logo'));

        return (new BrandResource(Brand::query()->create($data)))->response()->setStatusCode(201);
    }

    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($brand->loadCount('products'));
    }

    public function update(BrandRequest $request, Brand $brand): BrandResource
    {
        $data = $request->safe()->except('logo');

        if (($logo = $request->file('logo')) instanceof UploadedFile) {
            $old = $brand->logo_path;
            $data['logo_path'] = $this->storeLogo($logo);
        }

        $brand->update($data);

        if (isset($old)) {
            Storage::disk('public')->delete($old);
        }

        return new BrandResource($brand);
    }

    /** Soft delete; products keep their brand reference (nullOnDelete only on hard delete). */
    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return response()->json(['message' => __('messages.common.deleted')]);
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;

        while (Brand::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function storeLogo(?UploadedFile $file): ?string
    {
        return $file?->storeAs('brands', Str::uuid()->toString().'.'.($file->guessExtension() ?: 'png'), 'public');
    }
}
