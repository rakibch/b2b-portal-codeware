<?php

namespace Tests\Feature;

use App\Enums\CartItemSource;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Kyc\KycDocumentStorage;
use App\Services\Rfq\RfqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

/** Company A must never read or mutate Company B's data. */
class TenantIsolationTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    private User $buyerA;

    private User $buyerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        Notification::fake();
        Storage::fake('local');

        $this->buyerA = $this->buyer();
        $this->buyerB = $this->buyer();
    }

    public function test_cannot_view_or_respond_to_another_companys_rfq(): void
    {
        $product = Product::query()->where('sku', 'RIC-MIN-25')->firstOrFail();
        $rfq = app(RfqService::class)->create($this->buyerB, ['items' => [['product_id' => $product->id, 'quantity' => 1000]]]);

        $this->actingAsUser($this->buyerA);
        $this->getJson("/api/v1/buyer/rfqs/{$rfq->reference}")->assertStatus(403);
        $this->postJson("/api/v1/buyer/rfqs/{$rfq->reference}/cancel")->assertStatus(403);
        $this->getJson('/api/v1/buyer/rfqs')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cannot_touch_another_companys_addresses(): void
    {
        $address = $this->buyerB->company->addresses()->create([
            'type' => 'shipping', 'label' => 'B', 'contact_name' => 'B', 'contact_phone' => '01711111111',
            'address_line1' => 'x', 'city' => 'Dhaka', 'is_default' => true,
        ]);

        $this->actingAsUser($this->buyerA);
        $this->getJson("/api/v1/buyer/addresses/{$address->id}")->assertStatus(403);
        $this->putJson("/api/v1/buyer/addresses/{$address->id}", ['label' => 'hacked'])->assertStatus(403);
        $this->deleteJson("/api/v1/buyer/addresses/{$address->id}")->assertStatus(403);
        $this->assertSame('B', $address->fresh()->label);
    }

    public function test_cannot_modify_another_users_cart_item(): void
    {
        $product = Product::query()->where('sku', 'OIL-SOY-5L')->firstOrFail();
        app(CartService::class)->addMany($this->buyerB, [$product->id => 8], CartItemSource::Catalog);
        $item = $this->buyerB->cart->items()->first();

        $this->actingAsUser($this->buyerA);
        $this->patchJson("/api/v1/buyer/cart/items/{$item->id}", ['quantity' => 12])->assertStatus(403);
        $this->deleteJson("/api/v1/buyer/cart/items/{$item->id}")->assertStatus(403);
        $this->assertSame(8, $item->fresh()->quantity);
    }

    public function test_cannot_download_another_companys_kyc_document(): void
    {
        $document = app(KycDocumentStorage::class)->store(
            $this->buyerB->company,
            UploadedFile::fake()->create('l.pdf', 10, 'application/pdf'),
            DocumentType::TradeLicense,
            $this->buyerB,
        );

        $this->actingAsUser($this->buyerA);
        $this->get("/api/v1/documents/{$document->id}/download")->assertStatus(403);

        $this->actingAsUser($this->buyerB);
        $this->get("/api/v1/documents/{$document->id}/download")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAsUser($this->staff(Role::WarehouseStaff));
        $this->get("/api/v1/documents/{$document->id}/download")->assertStatus(403);
        $this->assertSame(DocumentStatus::Pending, $document->fresh()->status);
    }

    public function test_cannot_manage_members_of_another_company(): void
    {
        $memberB = $this->buyer($this->buyerB->company, Role::BuyerStaff);

        $this->actingAsUser($this->buyerA);
        $this->patchJson("/api/v1/buyer/team/members/{$memberB->id}", ['is_active' => false])->assertStatus(403);
        $this->assertTrue($memberB->fresh()->is_active);
    }

    public function test_company_profile_endpoint_only_returns_own_company(): void
    {
        $this->actingAsUser($this->buyerA);

        $this->getJson('/api/v1/buyer/company')
            ->assertOk()
            ->assertJsonPath('data.id', $this->buyerA->company->public_id);
    }
}
