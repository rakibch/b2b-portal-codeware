<?php

namespace App\Http\Resources;

use App\Models\CustomerGroup;
use Illuminate\Http\Request;

/** @mixin CustomerGroup */
class CustomerGroupResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $staff = $this->viewerIsStaff($request);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'discount_percent' => $this->when($staff, $this->discount_percent),
            'priority' => $this->when($staff, $this->priority),
            'is_active' => $this->when($staff, $this->is_active),
            'companies_count' => $this->whenCounted('companies'),
            'translations' => $this->when($staff, fn () => $this->getAllTranslations()),
        ];
    }
}
