<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // OrderPolicy::fulfil in controller
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            ...ShipmentDetailRules::rules(),
        ];
    }

    /** @return array<int, int> */
    public function quantities(): array
    {
        return collect($this->validated('items'))->mapWithKeys(fn ($i) => [(int) $i['order_item_id'] => (int) $i['quantity']])->all();
    }
}
