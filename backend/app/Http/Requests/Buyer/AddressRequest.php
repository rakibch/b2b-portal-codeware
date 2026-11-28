<?php

namespace App\Http\Requests\Buyer;

use App\Enums\AddressType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // CompanyAddressPolicy enforced in controller
    }

    public function rules(): array
    {
        $updating = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $req = $updating ? 'sometimes' : 'required';

        return [
            'type' => [$updating ? 'prohibited' : 'required', Rule::enum(AddressType::class)],
            'label' => [$req, 'string', 'max:100'],
            'contact_name' => [$req, 'string', 'max:255'],
            'contact_phone' => [$req, 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'address_line1' => [$req, 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:100'],
            'city' => [$req, 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
