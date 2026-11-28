<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

/** Only non-KYC fields are editable by the buyer; legal identifiers change via admin only. */
class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trade_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_person' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'website' => ['sometimes', 'nullable', 'url:https,http', 'max:255'],
        ];
    }
}
