<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Credit terms may be set during approval only by users holding credit.manage. */
class ApproveKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // CompanyPolicy::reviewKyc in controller
    }

    public function rules(): array
    {
        $canManageCredit = $this->user()->can('credit.manage');
        $credit = $canManageCredit ? 'sometimes' : 'prohibited';

        return [
            'credit_limit' => [$credit, 'decimal:0,2', 'min:0', 'max:999999999999'],
            'payment_terms_days' => [$credit, 'integer', 'min:0', 'max:365'],
            'credit_enabled' => [$credit, 'boolean'],
            'customer_group_id' => ['sometimes', 'nullable', 'integer', Rule::exists('customer_groups', 'id')->where('is_active', true)],
            'account_manager_id' => [$canManageCredit ? 'sometimes' : 'prohibited', 'nullable', 'integer', new AccountManagerRule],
        ];
    }
}
