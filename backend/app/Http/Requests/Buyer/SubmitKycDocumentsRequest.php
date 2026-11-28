<?php

namespace App\Http\Requests\Buyer;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class SubmitKycDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('company.manage_profile');
    }

    public function rules(): array
    {
        $file = ['file', 'mimes:'.implode(',', config('b2b.kyc.allowed_mimes')), 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.config('b2b.kyc.max_file_kb')];
        $rules = ['documents' => ['required', 'array', 'min:1', 'array:'.implode(',', DocumentType::values())]];

        foreach (DocumentType::values() as $type) {
            $rules["documents.{$type}"] = ['nullable', ...$file];
        }

        return $rules;
    }

    /** @return array<string, UploadedFile> */
    public function documents(): array
    {
        return array_filter((array) $this->file('documents', []));
    }
}
