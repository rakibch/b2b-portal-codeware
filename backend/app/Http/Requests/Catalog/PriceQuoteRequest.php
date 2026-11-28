<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/** Live price recalculation for one or many lines (catalog tier selector, cart steppers). */
class PriceQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('prices.view');
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:10000000'],
        ];
    }
}
