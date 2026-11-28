<?php

namespace App\Http\Requests\Buyer;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('company.manage_users');
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in([Role::BuyerAdmin->value, Role::BuyerStaff->value])],
            'order_approval_limit' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999'],
            'locale' => ['nullable', Rule::in(config('b2b.supported_locales'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }
}
