<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rfqs.quote');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.quoted_unit_price' => ['required', 'decimal:0,2', 'gt:0', 'max:999999999'],
            'valid_until' => ['required', 'date', 'after_or_equal:today'],
            'quote_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, string> */
    public function prices(): array
    {
        return collect($this->validated('items'))
            ->mapWithKeys(fn (array $i) => [(int) $i['id'] => (string) $i['quoted_unit_price']])
            ->all();
    }
}
