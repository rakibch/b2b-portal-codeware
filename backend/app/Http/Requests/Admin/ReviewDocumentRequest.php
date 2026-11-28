<?php

namespace App\Http\Requests\Admin;

use App\Enums\DocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([DocumentStatus::Verified->value, DocumentStatus::Rejected->value])],
            'notes' => ['required_if:status,rejected', 'nullable', 'string', 'max:1000'],
        ];
    }
}
