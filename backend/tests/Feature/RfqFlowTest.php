<?php

namespace Tests\Feature;

use App\Enums\RfqStatus;
use App\Enums\Role;
use App\Models\Product;
use App\Models\Rfq;
use App\Notifications\RfqQuotedNotification;
use App\Notifications\RfqSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class RfqFlowTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        Notification::fake();
    }

    private function createRfq($buyer): string
    {
        $this->actingAsUser($buyer);

        return $this->postJson('/api/v1/buyer/rfqs', [
            'buyer_notes' => 'Monthly bulk order',
            'items' => [
                ['product_id' => Product::query()->where('sku', 'RIC-MIN-25')->value('id'), 'quantity' => 2500, 'target_unit_price' => '70.00'],
                ['product_id' => Product::query()->where('sku', 'OIL-SOY-5L')->value('id'), 'quantity' => 400],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status.value', 'submitted')
            ->json('data.reference');
    }

    public function test_full_rfq_lifecycle(): void
    {
        $manager = $this->staff(Role::SalesManager);
        $company = $this->approvedCompany();
        $company->forceFill(['account_manager_id' => $manager->id])->save();
        $buyer = $this->buyer($company);

        $reference = $this->createRfq($buyer);
        Notification::assertSentTo($manager, RfqSubmittedNotification::class);

        $this->actingAsUser($manager);
        $this->postJson("/api/v1/admin/rfqs/{$reference}/review")->assertOk()->assertJsonPath('data.status.value', 'under_review');

        $items = Rfq::query()->where('reference', $reference)->firstOrFail()->items;
        $this->postJson("/api/v1/admin/rfqs/{$reference}/quote", [
            'items' => $items->map(fn ($i) => ['id' => $i->id, 'quoted_unit_price' => $i->quantity > 1000 ? '71.50' : '800.00'])->all(),
            'valid_until' => now()->addDays(7)->toDateString(),
            'quote_notes' => 'Delivery within 3 days',
            'internal_notes' => 'Margin approved by MD',
        ])->assertOk()
            ->assertJsonPath('data.status.value', 'quoted')
            ->assertJsonPath('data.quoted_total', '498750.00'); // 2500×71.50 + 400×800

        Notification::assertSentTo($buyer, RfqQuotedNotification::class);

        $this->actingAsUser($buyer);
        $this->getJson("/api/v1/buyer/rfqs/{$reference}")
            ->assertOk()
            ->assertJsonPath('data.can_accept', true)
            ->assertJsonMissingPath('data.internal_notes');

        $this->postJson("/api/v1/buyer/rfqs/{$reference}/accept")->assertOk()->assertJsonPath('data.status.value', 'accepted');
        $this->postJson("/api/v1/buyer/rfqs/{$reference}/accept")->assertStatus(422)->assertJsonPath('code', 'rfq_invalid_transition');
    }

    public function test_rfq_quantities_must_respect_moq_and_step(): void
    {
        $this->actingAsUser($this->buyer());

        $this->postJson('/api/v1/buyer/rfqs', ['items' => [
            ['product_id' => Product::query()->where('sku', 'RIC-MIN-25')->value('id'), 'quantity' => 30],
        ]])->assertStatus(422)->assertJsonPath('code', 'rfq_lines_invalid');
    }

    public function test_quote_must_price_every_item(): void
    {
        $reference = $this->createRfq($this->buyer());
        $item = Rfq::query()->where('reference', $reference)->firstOrFail()->items->first();

        $this->actingAsUser($this->staff(Role::SuperAdmin));
        $this->postJson("/api/v1/admin/rfqs/{$reference}/quote", [
            'items' => [['id' => $item->id, 'quoted_unit_price' => '70.00']],
            'valid_until' => now()->addDay()->toDateString(),
        ])->assertStatus(422)->assertJsonPath('code', 'rfq_quote_incomplete');
    }

    public function test_expired_quote_cannot_be_accepted_and_command_expires_it(): void
    {
        $buyer = $this->buyer();
        $reference = $this->createRfq($buyer);
        $rfq = Rfq::query()->where('reference', $reference)->firstOrFail();
        $rfq->forceFill(['status' => RfqStatus::Quoted, 'valid_until' => now()->subDays(2)->toDateString()])->save();
        $rfq->items()->update(['quoted_unit_price' => '70.00']);

        $this->artisan('rfqs:expire')->assertSuccessful();
        $this->assertSame(RfqStatus::Expired, $rfq->fresh()->status);

        $this->actingAsUser($buyer);
        $this->postJson("/api/v1/buyer/rfqs/{$reference}/accept")->assertStatus(422);
    }

    public function test_sales_manager_cannot_quote_other_managers_rfq(): void
    {
        $owner = $this->staff(Role::SalesManager);
        $company = $this->approvedCompany();
        $company->forceFill(['account_manager_id' => $owner->id])->save();
        $reference = $this->createRfq($this->buyer($company));

        $this->actingAsUser($this->staff(Role::SalesManager));
        $this->getJson("/api/v1/admin/rfqs/{$reference}")->assertStatus(403);
        $this->postJson("/api/v1/admin/rfqs/{$reference}/review")->assertStatus(403);
    }
}
