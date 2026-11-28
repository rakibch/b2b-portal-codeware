<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        Notification::fake();
    }

    private function confirmedOrderFor($buyer): string
    {
        $this->fillCart($buyer, ['OIL-SOY-5L' => 40]);
        $this->actingAsUser($buyer);
        $number = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))->json('data.order_number');
        $this->actingAsUser($this->staff(Role::SuperAdmin));
        $this->patchJson("/api/v1/admin/orders/{$number}/status", ['status' => 'confirmed'])->assertOk();

        return $number;
    }

    public function test_super_admin_sees_revenue_receivables_chart_and_low_stock(): void
    {
        $buyer = $this->buyer();
        $this->confirmedOrderFor($buyer);
        $this->pendingBuyer();

        $this->actingAsUser($this->staff(Role::SuperAdmin));
        $response = $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.revenue.this_month', '34860.00')
            ->assertJsonPath('data.receivables', '34860.00')
            ->assertJsonPath('data.kyc_pending', 1)
            ->assertJsonPath('data.operations.to_dispatch', 1)
            ->assertJsonCount(30, 'data.sales_chart');

        $this->assertSame('34860.00', collect($response->json('data.sales_chart'))->last()['total']);
        $this->assertContains('SPC-CHL-200', collect($response->json('data.low_stock'))->pluck('sku')->all());
    }

    public function test_sales_manager_kpis_exclude_other_managers_accounts(): void
    {
        $other = $this->staff(Role::SalesManager);
        $company = $this->approvedCompany();
        $company->forceFill(['account_manager_id' => $other->id])->save();
        $this->confirmedOrderFor($this->buyer($company));

        $this->actingAsUser($this->staff(Role::SalesManager));
        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.revenue.this_month', '0.00')
            ->assertJsonPath('data.receivables', '0.00');
    }

    public function test_warehouse_gets_operational_figures_only(): void
    {
        $this->actingAsUser($this->staff(Role::WarehouseStaff));
        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonMissingPath('data.revenue')
            ->assertJsonMissingPath('data.receivables')
            ->assertJsonStructure(['data' => ['operations', 'low_stock']]);

        $this->getJson('/api/v1/admin/lookups')->assertStatus(403);
    }

    public function test_lookups_for_kyc_reviewers(): void
    {
        $this->actingAsUser($this->staff(Role::SalesManager));
        $this->getJson('/api/v1/admin/lookups')->assertOk()->assertJsonCount(3, 'data.customer_groups');
    }
}
