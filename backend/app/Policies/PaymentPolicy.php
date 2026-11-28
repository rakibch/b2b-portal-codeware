<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isBuyer()) {
            return $user->company_id === $payment->company_id && $user->can('payments.submit');
        }

        return ($user->can('payments.view') || $user->can('payments.verify')) && $payment->company->isVisibleTo($user);
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $user->isStaff() && $user->can('payments.verify');
    }
}
