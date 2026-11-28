<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DispatchShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ShipmentPolicy::manage in controller
    }

    public function rules(): array
    {
        return ShipmentDetailRules::rules();
    }
}
