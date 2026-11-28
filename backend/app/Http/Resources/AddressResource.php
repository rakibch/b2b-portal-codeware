<?php

namespace App\Http\Resources;

use App\Models\CompanyAddress;
use Illuminate\Http\Request;

/** @mixin CompanyAddress */
class AddressResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->enum($this->type),
            'label' => $this->label,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'area' => $this->area,
            'city' => $this->city,
            'district' => $this->district,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
            'is_default' => $this->is_default,
            'full_address' => $this->fullAddress(),
        ];
    }
}
