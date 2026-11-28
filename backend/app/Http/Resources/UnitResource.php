<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;

/** @mixin Unit */
class UnitResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'is_active' => $this->is_active,
            'translations' => $this->when($this->viewerIsStaff($request), fn () => $this->getAllTranslations()),
        ];
    }
}
