<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('catalog.manage');
    }

    public function rules(): array
    {
        $brand = $this->route('brand');
        $req = $brand ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('brands', 'slug')->ignore($brand)],
            'is_active' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ];
    }
}
