<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.adjust');
    }

    public function rules(): array
    {
        return [
            'delta' => ['required', 'integer', 'not_in:0', 'min:-10000000', 'max:10000000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
