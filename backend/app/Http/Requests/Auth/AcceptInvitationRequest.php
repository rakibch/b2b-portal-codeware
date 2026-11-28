<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'designation' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'locale' => ['nullable', Rule::in(config('b2b.supported_locales'))],
        ];
    }
}
