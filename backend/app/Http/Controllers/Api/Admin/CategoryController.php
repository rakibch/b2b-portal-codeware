<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->withCount('products')->orderBy('sort_order')->get());
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $data = $request->safe()->except('image');
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']['en']);
        $data['image_path'] = $this->storeImage($request->file('image'));

        return (new CategoryResource(Category::query()->create($data)))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->loadCount('products'));
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $data = $request->safe()->except('image');

        if (($image = $request->file('image')) instanceof UploadedFile) {
            $old = $category->image_path;
            $data['image_path'] = $this->storeImage($image);
        }

        $category->update($data);

        if (isset($old)) {
            Storage::disk('public')->delete($old);
        }

        return new CategoryResource($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            throw BusinessRuleException::make('category_in_use');
        }

        $category->delete();

        return response()->json(['message' => __('messages.common.deleted')]);
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;

        while (Category::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function storeImage(?UploadedFile $file): ?string
    {
        return $file?->storeAs('categories', Str::uuid()->toString().'.'.($file->guessExtension() ?: 'jpg'), 'public');
    }
}
