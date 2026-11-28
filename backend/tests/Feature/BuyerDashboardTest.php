<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class BuyerDashboardTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        Notification::fake();
    }

    public function test_dashboard_summarises_credit_orders_and_reorder_suggestions(): void
    {
        $buyer = $this->buyer($this->approvedCompany(null, '100000.00'));
        $this->fillCart($buyer, ['OIL-SOY-5L' => 40]);
        $this->actingAsUser($buyer);
        $number = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))->json('data.order_number');

        $this->actingAsUser($this->staff(Role::SuperAdmin));
        $this->patchJson("/api/v1/admin/orders/{$number}/status", ['status' => 'confirmed'])->assertOk();

        $this->actingAsUser($buyer);
        $this->getJson('/api/v1/buyer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.credit.limit', '100000.00')
            ->assertJsonPath('data.credit.outstanding', '34860.00')
            ->assertJsonPath('data.credit.available', '65140.00')
            ->assertJsonPath('data.stats.open_orders', 1)
            ->assertJsonPath('data.stats.outstanding_invoices', 1)
            ->assertJsonPath('data.stats.outstanding_amount', '34860.00')
            ->assertJsonPath('data.recent_orders.0.order_number', $number)
            ->assertJsonPath('data.quick_reorder.0.sku', 'OIL-SOY-5L')
            ->assertJsonPath('data.quick_reorder.0.suggested_quantity', 40);
    }

    public function test_checkout_preview_reports_shipping_credit_and_method_availability(): void
    {
        $buyer = $this->buyer($this->approvedCompany(null, '5000.00'));
        $buyer->forceFill(['order_approval_limit' => '1000.00'])->save();
        $buyer->syncRoles([Role::BuyerStaff->value]);
        $this->fillCart($buyer, ['OIL-SOY-5L' => 8]); // 6,760 + 338 VAT + 500 delivery
        $this->actingAsUser($buyer);

        $response = $this->getJson('/api/v1/buyer/checkout/preview')
            ->assertOk()
            ->assertJsonPath('data.totals.shipping_total', '500.00')
            ->assertJsonPath('data.totals.grand_total', '7598.00')
            ->assertJsonPath('data.requires_approval', true)
            ->assertJsonPath('data.below_minimum', false);

        $methods = collect($response->json('data.payment_methods'))->keyBy('value');
        $this->assertFalse($methods['credit']['available']);
        $this->assertNotNull($methods['credit']['reason']);
        $this->assertTrue($methods['cod']['available']);
    }

    public function test_pending_buyer_cannot_open_dashboard(): void
    {
        $this->actingAsUser($this->pendingBuyer());
        $this->getJson('/api/v1/buyer/dashboard')->assertStatus(403)->assertJsonPath('code', 'kyc_not_approved');
    }
}
