<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $company->isVisibleTo($user);
    }

    /** Buyer Admin edits non-KYC profile fields of their own company. */
    public function update(User $user, Company $company): bool
    {
        return $user->isBuyer() && $user->company_id === $company->id && $user->can('company.manage_profile');
    }

    public function reviewKyc(User $user, Company $company): bool
    {
        return $user->isStaff() && $user->can('kyc.review') && $company->isVisibleTo($user);
    }

    public function manageCredit(User $user, Company $company): bool
    {
        return $user->isStaff() && $user->can('credit.manage');
    }

    public function managePricing(User $user, Company $company): bool
    {
        return $user->isStaff() && $user->can('pricing.manage');
    }
}
