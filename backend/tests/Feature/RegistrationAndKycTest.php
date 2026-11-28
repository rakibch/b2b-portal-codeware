<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Notifications\KycApprovedNotification;
use App\Notifications\KycSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class RegistrationAndKycTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        Storage::fake('local');
        Notification::fake();
    }

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'company' => [
                'name' => 'Rajshahi Traders',
                'business_type' => 'wholesaler',
                'contact_person' => 'Abul Kalam',
                'email' => 'info@rajtraders.example.com',
                'phone' => '01712345678',
                'trade_license_no' => 'trad/rcc/1234/2026',
                'tin_no' => '123456789012',
            ],
            'user' => [
                'name' => 'Abul Kalam',
                'email' => 'Abul@RajTraders.example.com',
                'phone' => '01712345678',
                'password' => 'Secret1234',
                'password_confirmation' => 'Secret1234',
                'locale' => 'bn',
            ],
            'billing_address' => ['contact_name' => 'Abul', 'contact_phone' => '01712345678', 'address_line1' => '12 Shaheb Bazar', 'city' => 'Rajshahi', 'postal_code' => '6100'],
            'delivery_addresses' => [
                ['label' => 'Godown', 'contact_name' => 'Karim', 'contact_phone' => '01812345678', 'address_line1' => '5 Station Road', 'city' => 'Rajshahi'],
            ],
            'documents' => [
                'trade_license' => UploadedFile::fake()->create('license.pdf', 200, 'application/pdf'),
                'nid' => UploadedFile::fake()->image('nid.jpg'),
            ],
            'accept_terms' => true,
        ], $override);
    }

    public function test_registration_creates_pending_company_with_private_documents(): void
    {
        $reviewer = $this->staff(Role::SalesManager);

        $response = $this->post('/api/v1/auth/register', $this->payload())
            ->assertCreated()
            ->assertJsonPath('user.company.kyc.status.value', 'pending')
            ->assertJsonPath('user.can_see_prices', false);

        $this->assertNotEmpty($response->json('token'));

        $company = Company::query()->where('trade_license_no', 'TRAD/RCC/1234/2026')->firstOrFail();
        $this->assertSame(KycStatus::Pending, $company->kyc_status);
        $this->assertSame('0.00', (string) $company->credit_limit);
        $this->assertCount(2, $company->addresses);
        $this->assertCount(2, $company->documents);

        $user = User::query()->where('email', 'abul@rajtraders.example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::BuyerAdmin->value));

        foreach ($company->documents as $document) {
            Storage::disk('local')->assertExists($document->path);
            $this->assertStringStartsWith("kyc/{$company->public_id}/", $document->path);
            $this->assertStringNotContainsString('license', $document->path); // server-generated names
        }

        Notification::assertSentTo($reviewer, KycSubmittedNotification::class);
    }

    public function test_registration_requires_mandatory_documents_and_rejects_bad_files(): void
    {
        $payload = $this->payload();
        unset($payload['documents']['nid']);
        $payload['documents']['trade_license'] = UploadedFile::fake()->create('evil.php', 10, 'application/x-php');

        $this->post('/api/v1/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['documents.nid', 'documents.trade_license']);

        $this->assertSame(0, Company::query()->count());
    }

    public function test_registration_rejects_duplicate_trade_license(): void
    {
        $this->post('/api/v1/auth/register', $this->payload())->assertCreated();

        $this->post('/api/v1/auth/register', $this->payload(['user' => ['email' => 'other@example.com']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company.trade_license_no']);
    }

    public function test_pending_buyer_sees_catalog_without_prices_and_cannot_transact(): void
    {
        $this->actingAsUser($this->pendingBuyer());

        $this->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonPath('data.0.prices_visible', false)
            ->assertJsonMissingPath('data.0.list_price')
            ->assertJsonMissingPath('data.0.pricing');

        $this->getJson('/api/v1/buyer/cart')->assertStatus(403)->assertJsonPath('code', 'kyc_not_approved');
        $this->postJson('/api/v1/catalog/price-quote', ['lines' => [['product_id' => 1, 'quantity' => 10]]])->assertStatus(403);
    }

    public function test_sales_manager_can_approve_but_not_set_credit_terms(): void
    {
        $company = $this->pendingBuyer()->company;
        $this->actingAsUser($this->staff(Role::SalesManager));

        $this->postJson("/api/v1/admin/kyc/{$company->public_id}/approve", ['credit_limit' => '100000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('credit_limit');

        $this->postJson("/api/v1/admin/kyc/{$company->public_id}/approve")
            ->assertOk()
            ->assertJsonPath('data.kyc.status.value', 'approved');

        $this->assertFalse($company->fresh()->credit_enabled);
    }

    public function test_super_admin_approves_with_credit_terms_and_buyer_is_notified(): void
    {
        $buyer = $this->pendingBuyer();
        $company = $buyer->company;
        $manager = $this->staff(Role::SalesManager);
        $this->actingAsUser($this->staff(Role::SuperAdmin));

        $this->postJson("/api/v1/admin/kyc/{$company->public_id}/approve", [
            'credit_limit' => '250000.00',
            'payment_terms_days' => 30,
            'customer_group_id' => CustomerGroup::query()->where('code', 'gold')->value('id'),
            'account_manager_id' => $manager->id,
        ])->assertOk()
            ->assertJsonPath('data.credit.credit_limit', '250000.00')
            ->assertJsonPath('data.credit.available_credit', '250000.00')
            ->assertJsonPath('data.credit.credit_enabled', true);

        Notification::assertSentTo($buyer, KycApprovedNotification::class);

        $this->actingAsUser($buyer->fresh());
        $this->getJson('/api/v1/catalog/products?per_page=1')->assertJsonPath('data.0.prices_visible', true);
    }

    public function test_account_manager_must_be_sales_staff(): void
    {
        $company = $this->pendingBuyer()->company;
        $warehouse = $this->staff(Role::WarehouseStaff);
        $this->actingAsUser($this->staff(Role::SuperAdmin));

        $this->postJson("/api/v1/admin/kyc/{$company->public_id}/approve", ['account_manager_id' => $warehouse->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('account_manager_id');
    }

    public function test_reject_then_resubmit_returns_company_to_queue(): void
    {
        $buyer = $this->pendingBuyer();
        $this->actingAsUser($this->staff(Role::SuperAdmin));
        $this->postJson("/api/v1/admin/kyc/{$buyer->company->public_id}/reject", ['reason' => 'Trade license is blurry'])
            ->assertOk()->assertJsonPath('data.kyc.status.value', 'rejected');

        $this->actingAsUser($buyer->fresh());
        $this->post('/api/v1/buyer/company/documents', [
            'documents' => ['trade_license' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')],
        ])->assertOk();

        $this->assertSame(KycStatus::Pending, $buyer->company->fresh()->kyc_status);
    }

    public function test_sales_manager_cannot_see_companies_assigned_to_another_manager(): void
    {
        $other = $this->staff(Role::SalesManager);
        $company = $this->approvedCompany();
        $company->forceFill(['account_manager_id' => $other->id])->save();

        $this->actingAsUser($this->staff(Role::SalesManager));
        $this->getJson("/api/v1/admin/companies/{$company->public_id}")->assertStatus(403);
        $this->getJson('/api/v1/admin/companies')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_warehouse_staff_cannot_review_kyc(): void
    {
        $company = $this->pendingBuyer()->company;
        $this->actingAsUser($this->staff(Role::WarehouseStaff));

        $this->postJson("/api/v1/admin/kyc/{$company->public_id}/approve")->assertStatus(403);
    }
}
