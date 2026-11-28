<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\TranslatableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    use TranslatableRules;

    public function authorize(): bool
    {
        return $this->user()->can('catalog.manage');
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $updating = $category !== null;

        return [
            ...$this->translatable('name', true, 255, $updating),
            ...$this->translatable('description', false, 2000),
            'slug' => [$updating ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('categories', 'slug')->ignore($category)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at'), Rule::notIn([$category?->id])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ];
    }
}
