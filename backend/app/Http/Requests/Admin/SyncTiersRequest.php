<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncTiersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pricing.manage');
    }

    public function rules(): array
    {
        return TierRules::rules('tiers');
    }
}
