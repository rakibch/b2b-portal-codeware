<?php

namespace App\Policies;

use App\Models\CompanyInvitation;
use App\Models\User;

class CompanyInvitationPolicy
{
    public function delete(User $user, CompanyInvitation $invitation): bool
    {
        return $user->isBuyer()
            && $user->company_id === $invitation->company_id
            && $user->can('company.manage_users');
    }
}
