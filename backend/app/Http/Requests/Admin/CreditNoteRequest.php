<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ledger.adjust');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'invoice_number' => ['nullable', 'string', 'max:32'],
        ];
    }
}
