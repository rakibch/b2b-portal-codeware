<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

trait BuildsPortal
{
    protected function seedPortal(): void
    {
        $this->seed([RolePermissionSeeder::class, CustomerGroupSeeder::class, CatalogSeeder::class]);
    }

    protected function staff(Role $role = Role::SuperAdmin): User
    {
        $user = User::factory()->staff()->create();
        $user->assignRole($role->value);

        return $user;
    }

    protected function approvedCompany(?string $groupCode = null, string $creditLimit = '500000.00'): Company
    {
        return Company::factory()->approved($creditLimit)->create([
            'customer_group_id' => $groupCode ? CustomerGroup::query()->where('code', $groupCode)->value('id') : null,
        ]);
    }

    protected function buyer(?Company $company = null, Role $role = Role::BuyerAdmin): User
    {
        $company ??= $this->approvedCompany();
        $user = User::factory()->forCompany($company)->create();
        $user->assignRole($role->value);

        return $user;
    }

    protected function pendingBuyer(): User
    {
        return $this->buyer(Company::factory()->create());
    }

    protected function actingAsUser(User $user): User
    {
        Sanctum::actingAs($user, ['*']);

        return $user;
    }
}
