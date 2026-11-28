<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payments.submit');
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(['bank_transfer', 'cheque'])],
            'order_number' => ['nullable', 'string', 'max:32'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999999999'],
            'transaction_ref' => ['required_if:method,bank_transfer', 'nullable', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:100'],
            'cheque_number' => ['required_if:method,cheque', 'nullable', 'string', 'max:50'],
            'deposit_date' => ['required', 'date', 'before_or_equal:today', 'after:-1 year'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'slip' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
        ];
    }
}
