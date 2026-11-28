<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pricing.manage');
    }

    public function rules(): array
    {
        $price = $this->route('price');
        $company = $this->route('company');

        return [
            'product_id' => [
                $price ? 'prohibited' : 'required',
                'integer',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
                Rule::unique('company_product_prices', 'product_id')->where('company_id', $company?->id),
            ],
            'unit_price' => [$price ? 'sometimes' : 'required', 'decimal:0,2', 'gt:0', 'max:999999999'],
            'min_quantity' => ['sometimes', 'integer', 'min:1', 'max:10000000'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}
