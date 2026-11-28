<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Buyer Admin manages OTHER members of their own company. */
    public function manageMember(User $actor, User $member): bool
    {
        return $actor->isBuyer()
            && $member->isBuyer()
            && $actor->company_id !== null
            && $actor->company_id === $member->company_id
            && $actor->id !== $member->id
            && $actor->can('company.manage_users');
    }
}
