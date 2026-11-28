<?php

namespace App\Http\Requests\Buyer;

use App\Enums\CartItemSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CartItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cart.manage');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.config('b2b.import.max_rows')],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'source' => ['nullable', Rule::enum(CartItemSource::class)],
            'mode' => ['nullable', Rule::in(['merge', 'replace'])],
        ];
    }

    /** @return array<int, int> product_id => quantity */
    public function quantities(): array
    {
        return collect($this->validated('items'))
            ->mapWithKeys(fn (array $i) => [(int) $i['product_id'] => (int) $i['quantity']])
            ->all();
    }
}
