<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

class QuickOrderImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cart.manage');
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,xls',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream,application/zip',
                'max:'.config('b2b.import.max_file_kb'),
            ],
        ];
    }
}
