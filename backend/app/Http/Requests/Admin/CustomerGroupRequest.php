<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\TranslatableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerGroupRequest extends FormRequest
{
    use TranslatableRules;

    public function authorize(): bool
    {
        return $this->user()->can('pricing.manage');
    }

    public function rules(): array
    {
        $group = $this->route('customer_group');
        $updating = $group !== null;

        return [
            'code' => [$updating ? 'sometimes' : 'required', 'string', 'max:32', 'alpha_dash:ascii', Rule::unique('customer_groups', 'code')->ignore($group)],
            ...$this->translatable('name', true, 100, $updating),
            ...$this->translatable('description', false, 500),
            'discount_percent' => ['sometimes', 'decimal:0,2', 'min:0', 'max:100'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
