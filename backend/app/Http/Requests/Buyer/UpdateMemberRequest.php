<?php

namespace App\Http\Requests\Buyer;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // UserPolicy::manageMember in controller
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::in([Role::BuyerAdmin->value, Role::BuyerStaff->value])],
            'order_approval_limit' => ['sometimes', 'nullable', 'decimal:0,2', 'min:0', 'max:999999999999'],
            'is_active' => ['sometimes', 'boolean'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
