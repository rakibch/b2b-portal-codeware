<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // CompanyPolicy::viewLedger in controller
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'format' => ['nullable', 'in:xlsx,csv'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->filled('from') && $this->filled('to') && Carbon::parse($this->input('from'))->diffInDays(Carbon::parse($this->input('to'))) > 366) {
                $validator->errors()->add('to', __('messages.validation.statement_range'));
            }
        }];
    }

    public function from(): ?Carbon
    {
        return $this->filled('from') ? Carbon::parse($this->validated('from'))->startOfDay() : null;
    }

    public function to(): ?Carbon
    {
        return $this->filled('to') ? Carbon::parse($this->validated('to'))->endOfDay() : null;
    }
}
