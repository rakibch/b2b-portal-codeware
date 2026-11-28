<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\TranslatableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    use TranslatableRules;

    public function authorize(): bool
    {
        return $this->user()->can('catalog.manage');
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $updating = $product !== null;
        $req = $updating ? 'sometimes' : 'required';

        return [
            'sku' => [$req, 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9\-_.]*$/', Rule::unique('products', 'sku')->ignore($product)],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[0-9A-Za-z\-]+$/', Rule::unique('products', 'barcode')->ignore($product)],
            'category_id' => [$req, 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id' => ['sometimes', 'nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'unit_id' => [$req, 'integer', Rule::exists('units', 'id')],
            ...$this->translatable('name', true, 255, $updating),
            ...$this->translatable('description', false, 5000),
            ...$this->translatable('pack_label', false, 50),
            'pack_size' => [$req, 'integer', 'min:1', 'max:100000'],
            'base_price' => [$req, 'decimal:0,2', 'gt:0', 'max:999999999'],
            'vat_percent' => [$req, 'decimal:0,2', 'min:0', 'max:100'],
            'moq' => [$req, 'integer', 'min:1', 'max:10000000'],
            'step_quantity' => [$req, 'integer', 'min:1', 'max:10000000'],
            'max_order_quantity' => ['sometimes', 'nullable', 'integer', 'gte:moq'],
            'stock_quantity' => [$updating ? 'prohibited' : 'sometimes', 'integer', 'min:0', 'max:4294967295'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=5000,max_height=5000'],
            'remove_image' => ['sometimes', 'boolean'],
            ...TierRules::rules('tiers', 'sometimes'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sku')) {
            $this->merge(['sku' => strtoupper(trim((string) $this->input('sku')))]);
        }
    }
}
