<?php

namespace Tests\Feature;

use App\Enums\RfqStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Rfq;
use App\Models\User;
use App\Services\Rfq\RfqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class FulfilmentAndDocumentsTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    private User $admin;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        Notification::fake();
        $this->admin = $this->staff(Role::SuperAdmin);
        $this->buyer = $this->buyer();
    }

    private function confirmedOrder(): Order
    {
        $this->fillCart($this->buyer, ['OIL-SOY-5L' => 40, 'TEA-PRM-400' => 96]);
        $this->actingAsUser($this->buyer);
        $number = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($this->buyer))->json('data.order_number');
        $this->actingAsUser($this->admin);
        $this->patchJson("/api/v1/admin/orders/{$number}/status", ['status' => 'confirmed'])->assertOk();

        return Order::query()->where('order_number', $number)->with('items')->firstOrFail();
    }

    public function test_partial_then_full_dispatch_and_delivery(): void
    {
        $order = $this->confirmedOrder();
        [$oil, $tea] = [$order->items[0], $order->items[1]];
        $warehouse = $this->actingAsUser($this->staff(Role::WarehouseStaff));

        $challan = $this->postJson("/api/v1/admin/orders/{$order->order_number}/shipments", [
            'items' => [['order_item_id' => $oil->id, 'quantity' => 20]],
            'carrier' => 'Sundarban Courier',
        ])->assertCreated()->json('data.challan_number');
        $this->assertSame('processing', $order->fresh()->status->value);

        // Cannot pack more than remaining (40 − 20 already packed).
        $this->postJson("/api/v1/admin/orders/{$order->order_number}/shipments", ['items' => [['order_item_id' => $oil->id, 'quantity' => 24]]])
            ->assertStatus(422)->assertJsonPath('code', 'shipment_quantity_exceeds');

        $this->postJson("/api/v1/admin/shipments/{$challan}/dispatch", ['vehicle_number' => 'DM-TA-11'])->assertOk();
        $this->assertSame('partially_dispatched', $order->fresh()->status->value);

        $second = $this->postJson("/api/v1/admin/orders/{$order->order_number}/shipments", ['items' => [
            ['order_item_id' => $oil->id, 'quantity' => 20],
            ['order_item_id' => $tea->id, 'quantity' => 96],
        ]])->assertCreated()->json('data.challan_number');
        $this->postJson("/api/v1/admin/shipments/{$second}/dispatch")->assertOk();
        $this->assertSame('dispatched', $order->fresh()->status->value);

        $this->postJson("/api/v1/admin/shipments/{$challan}/deliver", ['received_by' => 'Jamal'])->assertOk();
        $this->assertSame('dispatched', $order->fresh()->status->value);
        $this->postJson("/api/v1/admin/shipments/{$second}/deliver")->assertOk();
        $this->assertSame('delivered', $order->fresh()->status->value);

        // History captured every transition.
        $this->assertSame(
            ['pending', 'confirmed', 'processing', 'partially_dispatched', 'dispatched', 'delivered'],
            $order->statusHistory()->get()->map(fn ($h) => $h->to_status->value)->all(),
        );

        // Dispatched orders can no longer be cancelled.
        $this->actingAsUser($this->admin);
        $this->postJson("/api/v1/admin/orders/{$order->order_number}/cancel", ['reason' => 'too late'])->assertStatus(422);
        $this->assertNotNull($warehouse);
    }

    public function test_warehouse_cannot_see_pending_orders_or_confirm(): void
    {
        $this->fillCart($this->buyer, ['OIL-SOY-5L' => 8]);
        $this->actingAsUser($this->buyer);
        $number = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($this->buyer))->json('data.order_number');

        $this->actingAsUser($this->staff(Role::WarehouseStaff));
        $this->getJson("/api/v1/admin/orders/{$number}")->assertStatus(403);
        $this->patchJson("/api/v1/admin/orders/{$number}/status", ['status' => 'confirmed'])->assertStatus(403);
    }

    public function test_invoice_and_challan_pdfs_in_both_languages(): void
    {
        $order = $this->confirmedOrder();
        $invoice = $order->invoice;

        $this->actingAsUser($this->buyer);
        foreach (['en', 'bn'] as $lang) {
            $response = $this->get("/api/v1/invoices/{$invoice->invoice_number}/pdf?lang={$lang}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
            $this->assertStringContainsString("{$invoice->invoice_number}-{$lang}.pdf", $response->headers->get('Content-Disposition'));
        }
        $this->get("/api/v1/invoices/{$invoice->invoice_number}/pdf?lang=fr")->assertStatus(422);

        $this->actingAsUser($this->staff(Role::WarehouseStaff));
        $challan = $this->postJson("/api/v1/admin/orders/{$order->order_number}/shipments", ['items' => [['order_item_id' => $order->items[0]->id, 'quantity' => 40]]])->json('data.challan_number');
        $pdf = $this->get("/api/v1/shipments/{$challan}/challan?lang=bn")->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_quoted_rfq_converts_to_order_at_quoted_prices_once(): void
    {
        $rfq = app(RfqService::class)->create($this->buyer, ['items' => [['product_id' => $this->productId('OIL-SOY-5L'), 'quantity' => 100]]]);
        app(RfqService::class)->quote($rfq, $this->admin, [$rfq->items()->first()->id => '790.00'], today()->addDays(5)->toDateString(), null);

        $this->actingAsUser($this->buyer);
        $this->postJson("/api/v1/buyer/rfqs/{$rfq->reference}/order", $this->checkoutPayload($this->buyer))
            ->assertCreated()
            ->assertJsonPath('data.rfq_reference', $rfq->reference)
            ->assertJsonPath('data.items.0.unit_price', '790.00')
            ->assertJsonPath('data.items.0.price_source.value', 'rfq');

        $this->assertSame(RfqStatus::Converted, $rfq->fresh()->status);
        $this->postJson("/api/v1/buyer/rfqs/{$rfq->reference}/order", $this->checkoutPayload($this->buyer))
            ->assertStatus(422)->assertJsonPath('code', 'rfq_not_convertible');
        $this->assertSame(1, Order::query()->count());
        $this->assertInstanceOf(Rfq::class, $rfq);
    }
}
