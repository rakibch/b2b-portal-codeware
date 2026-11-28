<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Manual transitions only; dispatch/delivery are driven by shipments, cancel has its own endpoint. */
class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // OrderPolicy::updateStatus in controller
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([OrderStatus::Confirmed->value, OrderStatus::Processing->value])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
