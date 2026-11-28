<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isBuyer()) {
            return $user->company_id === $invoice->company_id && $user->can('invoices.view_company');
        }

        return $user->can('invoices.view') && $invoice->company->isVisibleTo($user);
    }
}
