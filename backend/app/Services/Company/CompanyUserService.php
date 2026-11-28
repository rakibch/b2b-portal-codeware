<?php

namespace App\Services\Company;

use App\Enums\Role;
use App\Enums\UserType;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Buyer Admin management of company sub-users (invite, accept, update, deactivate). */
class CompanyUserService
{
    public function invite(Company $company, User $inviter, array $data): CompanyInvitation
    {
        $email = mb_strtolower(trim($data['email']));

        if (User::withTrashed()->where('email', $email)->exists()) {
            throw BusinessRuleException::make('email_already_registered');
        }

        [$invitation, $token] = DB::transaction(function () use ($company, $inviter, $data, $email): array {
            // Revoke earlier open invitations for the same email to keep exactly one valid token.
            $company->invitations()->valid()->where('email', $email)->update(['revoked_at' => now()]);

            $invitation = new CompanyInvitation([
                'email' => $email,
                'name' => $data['name'] ?? null,
                'role' => $data['role'] ?? Role::BuyerStaff->value,
                'order_approval_limit' => $data['order_approval_limit'] ?? null,
                'invited_by' => $inviter->id,
                'expires_at' => now()->addHours((int) config('b2b.invitations.expires_hours')),
            ]);
            $token = $invitation->issueToken();
            $company->invitations()->save($invitation);

            AuditLog::record('company.user_invited', $company, [], ['email' => $email, 'role' => $invitation->role]);

            return [$invitation, $token];
        });

        $invitation->setRelation('company', $company)->setRelation('inviter', $inviter);

        Notification::route('mail', $email)
            ->notify((new CompanyInvitationNotification($invitation, $token))->locale($data['locale'] ?? $inviter->preferredLocale()));

        return $invitation;
    }

    public function revokeInvitation(CompanyInvitation $invitation): void
    {
        if ($invitation->accepted_at !== null) {
            throw BusinessRuleException::make('invitation_already_accepted');
        }

        $invitation->forceFill(['revoked_at' => now()])->save();
    }

    public function acceptInvitation(#[\SensitiveParameter] string $plainToken, array $data): User
    {
        return DB::transaction(function () use ($plainToken, $data): User {
            $invitation = CompanyInvitation::query()
                ->valid()
                ->where('token_hash', CompanyInvitation::hashToken($plainToken))
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw BusinessRuleException::make('invitation_invalid', [], 410);
            }

            if (User::withTrashed()->where('email', $invitation->email)->exists()) {
                throw BusinessRuleException::make('email_already_registered');
            }

            $user = new User([
                'name' => $data['name'],
                'email' => $invitation->email,
                'phone' => $data['phone'] ?? null,
                'designation' => $data['designation'] ?? null,
                'password' => $data['password'],
                'locale' => $data['locale'] ?? app()->getLocale(),
            ]);
            $user->forceFill([
                'user_type' => UserType::Buyer,
                'company_id' => $invitation->company_id,
                'is_active' => true,
                'order_approval_limit' => $invitation->order_approval_limit,
                'invited_by' => $invitation->invited_by,
                'email_verified_at' => now(), // possession of the emailed token proves the address
            ])->save();
            $user->assignRole(Role::from($invitation->role)->value);

            $invitation->forceFill(['accepted_at' => now()])->save();
            AuditLog::record('company.invitation_accepted', $user, [], ['company_id' => $invitation->company_id]);

            return $user;
        });
    }

    /** @param array{role?: string, order_approval_limit?: ?string, is_active?: bool, designation?: ?string} $data */
    public function updateMember(User $actor, User $member, array $data): User
    {
        return DB::transaction(function () use ($actor, $member, $data): User {
            $old = ['role' => $member->getRoleNames()->first(), 'is_active' => $member->is_active, 'order_approval_limit' => $member->order_approval_limit];

            $demoting = isset($data['role']) && $data['role'] !== Role::BuyerAdmin->value && $member->isBuyerAdmin();
            $deactivating = array_key_exists('is_active', $data) && ! $data['is_active'];

            if (($demoting || $deactivating) && $member->isBuyerAdmin()) {
                $this->assertNotLastAdmin($member);
            }

            if (array_key_exists('designation', $data)) {
                $member->designation = $data['designation'];
            }

            $member->forceFill(array_intersect_key($data, array_flip(['order_approval_limit', 'is_active'])))->save();

            if (isset($data['role'])) {
                $member->syncRoles([Role::from($data['role'])->value]);
            }

            if ($deactivating) {
                $member->tokens()->delete();
            }

            AuditLog::record('company.member_updated', $member, $old, $data + ['by' => $actor->id]);

            return $member->refresh();
        });
    }

    private function assertNotLastAdmin(User $member): void
    {
        $admins = User::query()
            ->where('company_id', $member->company_id)
            ->active()
            ->role(Role::BuyerAdmin->value)
            ->lockForUpdate() // lock admin rows, then count in PHP (FOR UPDATE + COUNT is invalid on PostgreSQL)
            ->pluck('users.id')
            ->count();

        if ($admins <= 1) {
            throw BusinessRuleException::make('last_company_admin');
        }
    }
}
