<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

class StoreRfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rfqs.create');
    }

    public function rules(): array
    {
        return [
            'buyer_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'items.*.target_unit_price' => ['nullable', 'decimal:0,2', 'gt:0', 'max:999999999'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
