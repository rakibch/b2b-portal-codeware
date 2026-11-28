<?php

namespace App\Policies;

use App\Models\Rfq;
use App\Models\User;

class RfqPolicy
{
    public function view(User $user, Rfq $rfq): bool
    {
        if ($user->isBuyer()) {
            return $user->company_id === $rfq->company_id;
        }

        return $user->can('rfqs.view') && $rfq->isVisibleTo($user);
    }

    public function respond(User $user, Rfq $rfq): bool
    {
        return $user->isBuyer() && $user->company_id === $rfq->company_id && $user->can('rfqs.respond');
    }

    /** Requester or a Buyer Admin of the same company may cancel. */
    public function cancel(User $user, Rfq $rfq): bool
    {
        return $user->isBuyer()
            && $user->company_id === $rfq->company_id
            && ($rfq->requested_by === $user->id || $user->isBuyerAdmin());
    }

    public function quote(User $user, Rfq $rfq): bool
    {
        return $user->isStaff() && $user->can('rfqs.quote') && $rfq->isVisibleTo($user);
    }
}
