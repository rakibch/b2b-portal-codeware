<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\TranslatableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    use TranslatableRules;

    public function authorize(): bool
    {
        return $this->user()->can('catalog.manage');
    }

    public function rules(): array
    {
        $unit = $this->route('unit');
        $updating = $unit !== null;

        return [
            'code' => [$updating ? 'sometimes' : 'required', 'string', 'max:16', 'alpha_dash:ascii', Rule::unique('units', 'code')->ignore($unit)],
            ...$this->translatable('name', true, 64, $updating),
            ...$this->translatable('short_name', true, 16, $updating),
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
