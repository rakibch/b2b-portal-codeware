<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    /** @var array<string, list<string>> role => permissions */
    public const MATRIX = [
        'super_admin' => ['*'],

        'sales_manager' => [
            'admin.dashboard.view',
            'companies.view',
            'kyc.review',
            'catalog.view',
            'prices.view',
            'orders.view',
            'orders.create_on_behalf',
            'orders.update_status',
            'orders.cancel',
            'rfqs.view',
            'rfqs.quote',
            'invoices.view',
            'ledger.view',
            'payments.view',
            'reports.view',
        ],

        'warehouse_staff' => [
            'admin.dashboard.view',
            'catalog.view',
            'inventory.view',
            'inventory.adjust',
            'orders.view',
            'orders.fulfil',
            'challans.print',
        ],

        'buyer_admin' => [
            'buyer.dashboard.view',
            'catalog.view',
            'prices.view',
            'cart.manage',
            'orders.place',
            'orders.view_company',
            'orders.approve',
            'orders.cancel_own',
            'rfqs.create',
            'rfqs.respond',
            'invoices.view_company',
            'ledger.view_company',
            'payments.submit',
            'company.manage_users',
            'company.manage_addresses',
            'company.manage_profile',
        ],

        'buyer_staff' => [
            'buyer.dashboard.view',
            'catalog.view',
            'prices.view',
            'cart.manage',
            'orders.place',
            'orders.view_company',
            'orders.cancel_own',
            'rfqs.create',
            'rfqs.respond',
            'invoices.view_company',
            'payments.submit',
        ],
    ];

    /** Permissions only Super Admin holds (granted via the '*' wildcard above). */
    public const ADMIN_ONLY = [
        'users.manage',
        'roles.manage',
        'companies.manage',
        'credit.manage',
        'pricing.manage',
        'catalog.manage',
        'payments.verify',
        'ledger.adjust',
        'settings.manage',
        'announcements.manage',
        'audit.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(self::MATRIX)
            ->flatten()
            ->merge(self::ADMIN_ONLY)
            ->reject(fn (string $p) => $p === '*')
            ->unique()
            ->values();

        foreach ($all as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        foreach (self::MATRIX as $roleName => $permissions) {
            $role = Role::findOrCreate(RoleEnum::from($roleName)->value, self::GUARD);
            $role->syncPermissions($permissions === ['*'] ? $all->all() : $permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
