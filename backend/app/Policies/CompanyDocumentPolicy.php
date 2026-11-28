<?php

namespace App\Policies;

use App\Models\CompanyDocument;
use App\Models\User;

class CompanyDocumentPolicy
{
    public function download(User $user, CompanyDocument $document): bool
    {
        $company = $document->company;

        if ($user->isBuyer()) {
            return $user->company_id === $company->id && $user->can('company.manage_profile');
        }

        return $user->can('kyc.review') && $company->isVisibleTo($user);
    }

    public function review(User $user, CompanyDocument $document): bool
    {
        return $user->isStaff() && $user->can('kyc.review') && $document->company->isVisibleTo($user);
    }
}
