<?php

namespace App\Policies;

use App\Models\CompanyAddress;
use App\Models\User;

class CompanyAddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBuyer() && $user->company_id !== null;
    }

    public function view(User $user, CompanyAddress $address): bool
    {
        return $user->isBuyer() && $user->company_id === $address->company_id;
    }

    public function create(User $user): bool
    {
        return $user->isBuyer() && $user->company_id !== null && $user->can('company.manage_addresses');
    }

    public function update(User $user, CompanyAddress $address): bool
    {
        return $this->view($user, $address) && $user->can('company.manage_addresses');
    }

    public function delete(User $user, CompanyAddress $address): bool
    {
        return $this->update($user, $address);
    }
}
