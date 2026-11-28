<?php

namespace App\Http\Requests\Admin;

final class ShipmentDetailRules
{
    public static function rules(): array
    {
        return [
            'carrier' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'vehicle_number' => ['nullable', 'string', 'max:32'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
