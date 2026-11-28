<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LedgerAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ledger.adjust');
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', 'in:debit,credit'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999999999'],
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
