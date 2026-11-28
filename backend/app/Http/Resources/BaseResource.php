<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    /** {"value": "pending", "label": "অপেক্ষমাণ"} — label localised by the active locale. */
    protected function enum(?BackedEnum $enum): ?array
    {
        if ($enum === null) {
            return null;
        }

        return ['value' => $enum->value, 'label' => method_exists($enum, 'label') ? $enum->label() : $enum->value];
    }

    protected function viewerIsStaff(Request $request): bool
    {
        return (bool) $request->user()?->isStaff();
    }

    protected function viewerCanSeePrices(Request $request): bool
    {
        return (bool) $request->user()?->canSeePrices();
    }

    protected function storageUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }
}
