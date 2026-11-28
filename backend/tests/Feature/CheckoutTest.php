<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        Notification::fake();
    }

    private function stock(string $sku): int
    {
        return Product::query()->where('sku', $sku)->value('stock_quantity');
    }

    public function test_checkout_reserves_stock_snapshots_prices_and_clears_cart(): void
    {
        $buyer = $this->actingAsUser($this->buyer());
        $this->fillCart($buyer, ['OIL-SOY-5L' => 40, 'TEA-PRM-400' => 96]);
        $before = $this->stock('OIL-SOY-5L');

        $response = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer, 'credit', ['po_number' => 'PO-1']))
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.items.0.unit_price', '830.00')
            ->assertJsonPath('data.items.0.price_source.value', 'tier')
            ->assertJsonPath('data.totals.shipping_total', '0.00'); // above free-shipping threshold

        // 40×830 + 96×228 = 33,200 + 21,888 = 55,088; VAT 1,660 + 1,641.60 = 3,301.60
        $response->assertJsonPath('data.totals.subtotal', '55088.00')
            ->assertJsonPath('data.totals.vat_total', '3301.60')
            ->assertJsonPath('data.totals.grand_total', '58389.60');

        $this->assertSame($before - 40, $this->stock('OIL-SOY-5L'));
        $this->getJson('/api/v1/buyer/cart')->assertJsonPath('data.item_count', 0);
        $this->assertSame('ফর্টিফাইড সয়াবিন তেল ৫ লিটার', Order::query()->first()->items->first()->getTranslation('product_name', 'bn'));
    }

    public function test_flat_shipping_fee_below_threshold_and_minimum_order(): void
    {
        $buyer = $this->actingAsUser($this->buyer());
        $this->fillCart($buyer, ['WTR-500' => 48]); // 48 × 14.20 = 681.60 < min 1,000

        $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))
            ->assertStatus(422)->assertJsonPath('code', 'order_below_minimum');

        $this->fillCart($buyer, ['OIL-SOY-5L' => 8]); // +6,760
        $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))
            ->assertCreated()->assertJsonPath('data.totals.shipping_total', '500.00');
    }

    public function test_credit_limit_blocks_checkout_atomically(): void
    {
        $buyer = $this->actingAsUser($this->buyer($this->approvedCompany(null, '10000.00')));
        $this->fillCart($buyer, ['OIL-SOY-5L' => 40]);
        $before = $this->stock('OIL-SOY-5L');

        $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))
            ->assertStatus(422)
            ->assertJsonPath('code', 'credit_limit_exceeded')
            ->assertJsonPath('context.available_credit', '10000.00');

        $this->assertSame($before, $this->stock('OIL-SOY-5L'));
        $this->assertSame(0, Order::query()->count());

        // Other methods still work.
        $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer, 'cod'))->assertCreated();
    }

    public function test_pending_uninvoiced_credit_orders_count_against_available_credit(): void
    {
        $buyer = $this->actingAsUser($this->buyer($this->approvedCompany(null, '40000.00')));

        $this->fillCart($buyer, ['OIL-SOY-5L' => 40]); // ≈ 34,860
        $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))->assertCreated();

        $this->fillCart($buyer, ['OIL-SOY-5L' => 8]); // ≈ 7,098 → total exposure > 40,000
        $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))
            ->assertStatus(422)->assertJsonPath('code', 'credit_limit_exceeded');
    }

    public function test_credit_on_hold_or_disabled_blocks_credit_checkout(): void
    {
        $company = $this->approvedCompany();
        $company->forceFill(['credit_on_hold' => true])->save();
        $buyer = $this->actingAsUser($this->buyer($company));
        $this->fillCart($buyer, ['OIL-SOY-5L' => 8]);

        $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))->assertStatus(422)->assertJsonPath('code', 'credit_on_hold');
    }

    public function test_cod_limit_and_foreign_address_are_rejected(): void
    {
        $buyer = $this->actingAsUser($this->buyer());
        $this->fillCart($buyer, ['RIC-MIN-25' => 3000]); // ≈ 217,500 > COD max 200,000

        $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer, 'cod'))
            ->assertStatus(422)->assertJsonPath('code', 'cod_limit_exceeded');

        $other = $this->approvedCompany();
        $this->postJson('/api/v1/buyer/orders', ['shipping_address_id' => $this->shippingAddress($other)->id, 'payment_method' => 'credit'])
            ->assertStatus(422)->assertJsonPath('code', 'shipping_address_invalid');
    }

    public function test_stock_changed_after_carting_is_caught_at_checkout(): void
    {
        $buyer = $this->actingAsUser($this->buyer());
        $this->fillCart($buyer, ['OIL-SOY-5L' => 40]);
        Product::query()->where('sku', 'OIL-SOY-5L')->update(['stock_quantity' => 10]);

        $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))
            ->assertStatus(422)
            ->assertJsonPath('code', 'checkout_lines_invalid')
            ->assertJsonPath('context.lines.0.errors.0.code', 'insufficient_stock');

        $this->assertSame(10, $this->stock('OIL-SOY-5L'));
    }

    public function test_sub_user_above_threshold_needs_buyer_admin_approval(): void
    {
        $company = $this->approvedCompany();
        $admin = $this->buyer($company);
        $officer = $this->buyer($company, Role::BuyerStaff);
        $officer->forceFill(['order_approval_limit' => '20000.00'])->save();

        $this->actingAsUser($officer);
        $this->fillCart($officer, ['OIL-SOY-5L' => 40]);
        $number = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($officer))
            ->assertCreated()
            ->assertJsonPath('data.approval_status.value', 'pending')
            ->json('data.order_number');

        // Staff cannot confirm before buyer approval (and the order is hidden from their queue).
        $this->actingAsUser($this->staff());
        $this->getJson('/api/v1/admin/orders')->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/admin/orders/{$number}/status", ['status' => 'confirmed'])
            ->assertStatus(422)->assertJsonPath('code', 'order_awaiting_buyer_approval');

        // Officer cannot approve own order.
        $this->actingAsUser($officer);
        $this->postJson("/api/v1/buyer/orders/{$number}/approve")->assertStatus(403);

        $this->actingAsUser($admin);
        $this->postJson("/api/v1/buyer/orders/{$number}/approve")->assertOk()->assertJsonPath('data.approval_status.value', 'approved');

        $this->actingAsUser($this->staff());
        $this->patchJson("/api/v1/admin/orders/{$number}/status", ['status' => 'confirmed'])->assertOk();
    }

    public function test_rejected_approval_cancels_and_restores_stock(): void
    {
        $company = $this->approvedCompany();
        $admin = $this->buyer($company);
        $officer = $this->buyer($company, Role::BuyerStaff);
        $officer->forceFill(['order_approval_limit' => '1000.00'])->save();
        $this->fillCart($officer, ['OIL-SOY-5L' => 40]);
        $before = $this->stock('OIL-SOY-5L');

        $this->actingAsUser($officer);
        $number = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($officer))->json('data.order_number');

        $this->actingAsUser($admin);
        $this->postJson("/api/v1/buyer/orders/{$number}/reject", ['reason' => 'Over monthly budget'])
            ->assertOk()->assertJsonPath('data.status.value', 'cancelled');

        $this->assertSame($before, $this->stock('OIL-SOY-5L'));
    }

    public function test_buyer_can_cancel_only_pending_orders(): void
    {
        $buyer = $this->actingAsUser($this->buyer());
        $this->fillCart($buyer, ['OIL-SOY-5L' => 40]);
        $before = $this->stock('OIL-SOY-5L');
        $number = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))->json('data.order_number');

        $this->postJson("/api/v1/buyer/orders/{$number}/cancel", ['reason' => 'Ordered by mistake'])->assertOk();
        $this->assertSame($before, $this->stock('OIL-SOY-5L'));

        $this->postJson("/api/v1/buyer/orders/{$number}/cancel", ['reason' => 'Again please'])->assertStatus(403);
    }

    public function test_sales_manager_places_order_on_behalf_of_assigned_buyer(): void
    {
        $manager = $this->staff(Role::SalesManager);
        $company = $this->approvedCompany();
        $company->forceFill(['account_manager_id' => $manager->id])->save();
        $buyer = $this->buyer($company);

        $this->actingAsUser($manager);
        $this->postJson("/api/v1/admin/companies/{$company->public_id}/orders", [
            'buyer_user_id' => $buyer->id,
            'shipping_address_id' => $this->shippingAddress($company)->id,
            'payment_method' => 'credit',
            'internal_notes' => 'Phone order',
            'items' => [['product_id' => $this->productId('TEA-PRM-400'), 'quantity' => 240]],
        ])->assertCreated()
            ->assertJsonPath('data.created_by_staff.id', $manager->id)
            ->assertJsonPath('data.items.0.unit_price', '222.00');

        // Another manager's company is off limits.
        $foreign = $this->approvedCompany();
        $foreign->forceFill(['account_manager_id' => $this->staff(Role::SalesManager)->id])->save();
        $this->postJson("/api/v1/admin/companies/{$foreign->public_id}/orders", [
            'buyer_user_id' => $this->buyer($foreign)->id,
            'shipping_address_id' => $this->shippingAddress($foreign)->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $this->productId('TEA-PRM-400'), 'quantity' => 24]],
        ])->assertStatus(403);
    }

    public function test_reorder_revalidates_prices_and_stock(): void
    {
        $buyer = $this->actingAsUser($this->buyer());
        $this->fillCart($buyer, ['OIL-SOY-5L' => 40, 'TEA-PRM-400' => 96]);
        $number = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))->json('data.order_number');

        Product::query()->where('sku', 'TEA-PRM-400')->update(['stock_quantity' => 0]);
        Product::query()->where('sku', 'OIL-SOY-5L')->update(['base_price' => '900.00']);

        $this->postJson("/api/v1/buyer/orders/{$number}/reorder")
            ->assertOk()
            ->assertJsonCount(1, 'added')
            ->assertJsonPath('skipped.0.sku', 'TEA-PRM-400')
            ->assertJsonPath('skipped.0.reason', 'insufficient_stock')
            ->assertJsonPath('cart.item_count', 1);
    }

    public function test_orders_are_isolated_between_companies(): void
    {
        $buyerA = $this->buyer();
        $this->fillCart($buyerA, ['OIL-SOY-5L' => 8]);
        $this->actingAsUser($buyerA);
        $number = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyerA))->json('data.order_number');

        $this->actingAsUser($this->buyer());
        $this->getJson("/api/v1/buyer/orders/{$number}")->assertStatus(403);
        $this->postJson("/api/v1/buyer/orders/{$number}/cancel", ['reason' => 'malicious'])->assertStatus(403);
        $this->postJson("/api/v1/buyer/orders/{$number}/reorder")->assertStatus(403);
        $this->getJson('/api/v1/buyer/orders')->assertJsonCount(0, 'data');
        $this->assertSame(OrderStatus::Pending, Order::query()->first()->status);
    }
}
