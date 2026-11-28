<?php

namespace App\Http\Requests\Auth;

use App\Enums\BusinessType;
use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Multipart B2B KYC registration. */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $file = ['file', 'mimes:'.implode(',', config('b2b.kyc.allowed_mimes')), 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.config('b2b.kyc.max_file_kb')];
        $phone = ['regex:/^(?:\+?88)?01[3-9]\d{8}$/'];

        return [
            'company.name' => ['required', 'string', 'max:255'],
            'company.trade_name' => ['nullable', 'string', 'max:255'],
            'company.business_type' => ['required', Rule::enum(BusinessType::class)],
            'company.contact_person' => ['required', 'string', 'max:255'],
            'company.email' => ['required', 'email:rfc', 'max:255'],
            'company.phone' => ['required', 'string', ...$phone],
            'company.trade_license_no' => ['required', 'string', 'max:64', 'unique:companies,trade_license_no'],
            'company.tin_no' => ['nullable', 'string', 'max:32', 'regex:/^[0-9\-]+$/', 'unique:companies,tin_no'],
            'company.bin_no' => ['nullable', 'string', 'max:32', 'regex:/^[0-9\-]+$/', 'unique:companies,bin_no'],
            'company.website' => ['nullable', 'url:https,http', 'max:255'],

            'user.name' => ['required', 'string', 'max:255'],
            'user.email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'user.phone' => ['required', 'string', ...$phone],
            'user.designation' => ['nullable', 'string', 'max:100'],
            'user.password' => ['required', 'string', 'confirmed', Password::defaults()],
            'user.locale' => ['nullable', Rule::in(config('b2b.supported_locales'))],

            'billing_address' => ['required', 'array'],
            ...$this->addressRules('billing_address'),

            'delivery_addresses' => ['required', 'array', 'min:1', 'max:20'],
            ...$this->addressRules('delivery_addresses.*', labelRequired: true),

            'documents' => ['required', 'array'],
            'documents.'.DocumentType::TradeLicense->value => ['required', ...$file],
            'documents.'.DocumentType::Nid->value => ['required', ...$file],
            'documents.'.DocumentType::TinCertificate->value => ['nullable', ...$file],
            'documents.'.DocumentType::BinCertificate->value => ['nullable', ...$file],
            'documents.'.DocumentType::Other->value => ['nullable', ...$file],

            'accept_terms' => ['accepted'],
        ];
    }

    private function addressRules(string $prefix, bool $labelRequired = false): array
    {
        return [
            "{$prefix}.label" => [$labelRequired ? 'required' : 'nullable', 'string', 'max:100'],
            "{$prefix}.contact_name" => ['required', 'string', 'max:255'],
            "{$prefix}.contact_phone" => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            "{$prefix}.address_line1" => ['required', 'string', 'max:255'],
            "{$prefix}.address_line2" => ['nullable', 'string', 'max:255'],
            "{$prefix}.area" => ['nullable', 'string', 'max:100'],
            "{$prefix}.city" => ['required', 'string', 'max:100'],
            "{$prefix}.district" => ['nullable', 'string', 'max:100'],
            "{$prefix}.postal_code" => ['nullable', 'string', 'regex:/^\d{4}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $user = (array) $this->input('user', []);
        $company = (array) $this->input('company', []);

        if (isset($user['email'])) {
            $user['email'] = mb_strtolower(trim((string) $user['email']));
        }
        if (isset($company['email'])) {
            $company['email'] = mb_strtolower(trim((string) $company['email']));
        }
        foreach (['trade_license_no', 'tin_no', 'bin_no'] as $key) {
            if (isset($company[$key])) {
                $company[$key] = strtoupper(trim((string) $company[$key]));
            }
        }

        $this->merge(['user' => $user, 'company' => $company]);
    }

    /** @return array<string, UploadedFile> */
    public function documents(): array
    {
        return array_filter((array) $this->file('documents', []));
    }

    public function attributes(): array
    {
        return [
            'company.name' => __('messages.attributes.company_name'),
            'company.trade_license_no' => __('messages.attributes.trade_license_no'),
            'company.tin_no' => __('messages.attributes.tin_no'),
            'company.bin_no' => __('messages.attributes.bin_no'),
            'user.email' => __('messages.attributes.email'),
            'user.password' => __('messages.attributes.password'),
            'documents.trade_license' => __('enums.document_type.trade_license'),
            'documents.nid' => __('enums.document_type.nid'),
        ];
    }
}
