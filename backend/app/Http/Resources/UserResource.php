<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/** @mixin User */
class UserResource extends BaseResource
{
    private bool $withPermissions = false;

    public function withPermissions(): static
    {
        $this->withPermissions = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'designation' => $this->designation,
            'locale' => $this->locale,
            'user_type' => $this->enum($this->user_type),
            'is_active' => $this->is_active,
            'order_approval_limit' => $this->order_approval_limit,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'permissions' => $this->when($this->withPermissions, fn () => $this->getAllPermissions()->pluck('name')->values()),
            'can_see_prices' => $this->when($this->withPermissions, fn () => $this->canSeePrices()),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'company' => new CompanyResource($this->whenLoaded('company')),
        ];
    }
}
