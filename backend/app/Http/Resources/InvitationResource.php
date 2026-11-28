<?php

namespace App\Http\Resources;

use App\Models\CompanyInvitation;
use Illuminate\Http\Request;

/** @mixin CompanyInvitation */
class InvitationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $status = match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };

        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'order_approval_limit' => $this->order_approval_limit,
            'status' => $status,
            'expires_at' => $this->expires_at->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'invited_by' => $this->whenLoaded('inviter', fn () => $this->inviter?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
