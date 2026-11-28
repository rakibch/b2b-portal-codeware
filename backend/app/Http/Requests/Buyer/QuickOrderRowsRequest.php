<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

/** Spreadsheet-like quick order pad rows. Row-level problems are reported, not rejected. */
class QuickOrderRowsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cart.manage');
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:'.config('b2b.import.max_rows')],
            'rows.*.row' => ['nullable', 'integer', 'min:1'],
            'rows.*.sku' => ['nullable', 'string', 'max:64'],
            'rows.*.product_id' => ['nullable', 'integer', 'min:1'],
            'rows.*.quantity' => ['nullable'],
        ];
    }
}
