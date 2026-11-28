<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeliverShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ShipmentPolicy::manage in controller
    }

    public function rules(): array
    {
        return ['received_by' => ['nullable', 'string', 'max:100']];
    }
}
