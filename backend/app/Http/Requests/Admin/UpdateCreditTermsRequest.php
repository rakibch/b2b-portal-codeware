<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreditTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('credit.manage');
    }

    public function rules(): array
    {
        return [
            'credit_limit' => ['sometimes', 'decimal:0,2', 'min:0', 'max:999999999999'],
            'payment_terms_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'credit_enabled' => ['sometimes', 'boolean'],
            'credit_on_hold' => ['sometimes', 'boolean'],
            'customer_group_id' => ['sometimes', 'nullable', 'integer', Rule::exists('customer_groups', 'id')],
            'account_manager_id' => ['sometimes', 'nullable', 'integer', new AccountManagerRule],
        ];
    }
}
