<?php

namespace Tests\Concerns;

use App\Enums\CartItemSource;
use App\Enums\Role;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Laravel\Sanctum\Sanctum;

trait BuildsPortal
{
    protected function seedPortal(): void
    {
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class, CustomerGroupSeeder::class, CatalogSeeder::class]);
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

    protected function shippingAddress(Company $company): CompanyAddress
    {
        return $company->addresses()->where('type', 'shipping')->first() ?? $company->addresses()->create([
            'type' => 'shipping',
            'label' => 'Main Warehouse',
            'contact_name' => 'Store Keeper',
            'contact_phone' => '01711111111',
            'address_line1' => '10 Test Road',
            'city' => 'Dhaka',
            'is_default' => true,
        ]);
    }

    protected function productId(string $sku): int
    {
        return Product::query()->where('sku', $sku)->value('id');
    }

    /** @param array<string, int> $skuQuantities */
    protected function fillCart(User $buyer, array $skuQuantities): void
    {
        $lines = [];
        foreach ($skuQuantities as $sku => $qty) {
            $lines[$this->productId($sku)] = $qty;
        }

        app(CartService::class)->addMany($buyer, $lines, CartItemSource::Catalog);
    }

    protected function checkoutPayload(User $buyer, string $method = 'credit', array $extra = []): array
    {
        return ['shipping_address_id' => $this->shippingAddress($buyer->company)->id, 'payment_method' => $method] + $extra;
    }
}
