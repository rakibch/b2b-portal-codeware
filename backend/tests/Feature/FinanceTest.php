<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\InvoiceOverdueNotification;
use App\Services\Payment\GatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        Notification::fake();
        Storage::fake('local');
        config(['payments.gateways.mock.enabled' => true, 'payments.gateways.mock.secret' => 'test-secret']);
        $this->admin = $this->staff(Role::SuperAdmin);
    }

    /** Places (and optionally confirms) an order; returns the order number. */
    private function order(User $buyer, string $method = 'credit', array $cart = ['OIL-SOY-5L' => 40], bool $confirm = true): string
    {
        $this->fillCart($buyer, $cart);
        $this->actingAsUser($buyer);
        $number = $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer, $method))->assertCreated()->json('data.order_number');

        if ($confirm) {
            $this->actingAsUser($this->admin);
            $this->patchJson("/api/v1/admin/orders/{$number}/status", ['status' => 'confirmed'])->assertOk();
        }

        return $number;
    }

    private function balance(Company $company): string
    {
        return (string) $company->fresh()->outstanding_balance;
    }

    public function test_confirmation_issues_invoice_with_terms_and_posts_ledger_debit(): void
    {
        $buyer = $this->buyer($this->approvedCompany(null, '500000.00'));
        $number = $this->order($buyer);

        $order = Order::query()->where('order_number', $number)->firstOrFail();
        $invoice = $order->invoice;

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertSame(today()->addDays(30)->toDateString(), $invoice->due_date->toDateString());
        $this->assertSame('34860.00', (string) $invoice->grand_total);
        $this->assertSame('34860.00', $this->balance($buyer->company));

        $entry = LedgerEntry::query()->where('company_id', $buyer->company_id)->sole();
        $this->assertSame('34860.00', (string) $entry->debit);
        $this->assertStringContainsString($invoice->invoice_number, $entry->getTranslation('description', 'bn'));
    }

    public function test_cancelling_confirmed_order_voids_invoice_reverses_ledger_and_restores_stock(): void
    {
        $buyer = $this->buyer();
        $number = $this->order($buyer);

        $this->actingAsUser($this->admin);
        $this->postJson("/api/v1/admin/orders/{$number}/cancel", ['reason' => 'Customer request'])->assertOk();

        $order = Order::query()->where('order_number', $number)->firstOrFail();
        $this->assertSame(InvoiceStatus::Void, $order->invoice->status);
        $this->assertSame('0.00', $this->balance($buyer->company));
        $this->assertSame(2, LedgerEntry::query()->where('company_id', $buyer->company_id)->count());
    }

    public function test_bank_transfer_submission_and_verification_settles_invoice(): void
    {
        $buyer = $this->buyer();
        $number = $this->order($buyer);
        $order = Order::query()->where('order_number', $number)->firstOrFail();

        $this->actingAsUser($buyer);
        $reference = $this->post('/api/v1/buyer/payments', [
            'method' => 'bank_transfer',
            'order_number' => $order->order_number,
            'amount' => '34860.00',
            'transaction_ref' => 'NPSB-778812',
            'bank_name' => 'Example Bank',
            'deposit_date' => today()->toDateString(),
            'slip' => UploadedFile::fake()->create('slip.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.status.value', 'pending')->json('data.reference');

        $this->assertSame('pending_verification', $order->fresh()->payment_status->value);
        $this->assertSame('34860.00', $this->balance($buyer->company)); // not yet in the ledger

        $this->actingAsUser($this->admin);
        $this->postJson("/api/v1/admin/payments/{$reference}/verify")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'verified')
            ->assertJsonPath('data.allocations.0.amount', '34860.00');

        $this->assertSame('0.00', $this->balance($buyer->company));
        $this->assertSame(InvoiceStatus::Paid, $order->invoice->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status->value);

        // Verifying twice is rejected; the slip is downloadable only by owner/staff.
        $this->postJson("/api/v1/admin/payments/{$reference}/verify")->assertOk(); // idempotent
        $this->get("/api/v1/payments/{$reference}/slip")->assertOk();
        $this->actingAsUser($this->buyer());
        $this->get("/api/v1/payments/{$reference}/slip")->assertStatus(403);
    }

    public function test_on_account_payment_is_allocated_fifo_by_due_date(): void
    {
        $buyer = $this->buyer();
        $first = $this->order($buyer, 'credit', ['OIL-SOY-5L' => 8]);   // 6,760 + 338 VAT + 500 delivery = 7,598.00
        $second = $this->order($buyer, 'credit', ['OIL-SOY-5L' => 8]);  // 7,598.00

        $this->actingAsUser($buyer);
        $reference = $this->post('/api/v1/buyer/payments', [
            'method' => 'cheque', 'amount' => '10000.00', 'cheque_number' => 'CHQ-1001', 'bank_name' => 'Example Bank',
            'deposit_date' => today()->toDateString(), 'slip' => UploadedFile::fake()->image('cheque.jpg'),
        ])->assertCreated()->json('data.reference');

        $this->actingAsUser($this->admin);
        $this->postJson("/api/v1/admin/payments/{$reference}/verify")->assertOk();

        $inv1 = Order::query()->where('order_number', $first)->first()->invoice;
        $inv2 = Order::query()->where('order_number', $second)->first()->invoice;
        $this->assertSame(InvoiceStatus::Paid, $inv1->status);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $inv2->status);
        $this->assertSame('2402.00', (string) $inv2->amount_paid);
        $this->assertSame('5196.00', $this->balance($buyer->company)); // 15,196 − 10,000
    }

    public function test_rejected_payment_does_not_touch_ledger(): void
    {
        $buyer = $this->buyer();
        $this->order($buyer);

        $this->actingAsUser($buyer);
        $reference = $this->post('/api/v1/buyer/payments', [
            'method' => 'bank_transfer', 'amount' => '5000', 'transaction_ref' => 'X1', 'bank_name' => 'B',
            'deposit_date' => today()->toDateString(), 'slip' => UploadedFile::fake()->image('s.png'),
        ])->json('data.reference');

        $this->actingAsUser($this->admin);
        $this->postJson("/api/v1/admin/payments/{$reference}/reject", ['reason' => 'Amount not received'])->assertOk();
        $this->postJson("/api/v1/admin/payments/{$reference}/verify")->assertStatus(422)->assertJsonPath('code', 'payment_not_pending');
        $this->assertSame('34860.00', $this->balance($buyer->company));
    }

    public function test_cod_cash_collection_records_verified_payment(): void
    {
        $buyer = $this->buyer();
        $number = $this->order($buyer, 'cod');

        $this->actingAsUser($this->admin);
        $this->postJson("/api/v1/admin/orders/{$number}/cash-payments", ['amount' => '34860.00'])
            ->assertCreated()->assertJsonPath('data.method.value', 'cash')->assertJsonPath('data.status.value', 'verified');

        $this->assertSame('paid', Order::query()->where('order_number', $number)->first()->payment_status->value);
        $this->assertSame('0.00', $this->balance($buyer->company));
    }

    public function test_gateway_flow_signed_callback_idempotency_and_tampering(): void
    {
        $buyer = $this->buyer();
        $number = $this->order($buyer, 'gateway', confirm: false);

        $this->actingAsUser($this->admin);
        $this->patchJson("/api/v1/admin/orders/{$number}/status", ['status' => 'confirmed'])
            ->assertStatus(422)->assertJsonPath('code', 'order_gateway_unpaid');

        $this->actingAsUser($buyer);
        $redirect = $this->postJson("/api/v1/buyer/orders/{$number}/pay-online")->assertCreated()->json('redirect_url');
        $this->assertStringContainsString('signature=', $redirect);

        $payment = Payment::query()->where('gateway', 'mock')->sole();
        $mock = app(GatewayManager::class)->driver('mock');

        // Tampered amount → signature invalid.
        $tampered = $mock->signedPayload($payment, $payment->gateway_transaction_id, 'success');
        $tampered['amount'] = '1.00';
        $this->postJson('/api/v1/payments/mock/callback', $tampered)->assertStatus(400);

        $payload = $mock->signedPayload($payment, $payment->gateway_transaction_id, 'success');
        $this->postJson('/api/v1/payments/mock/callback', $payload)->assertOk()->assertJsonPath('status', 'verified');
        $this->postJson('/api/v1/payments/mock/callback', $payload)->assertOk()->assertJsonPath('status', 'verified'); // replay-safe

        $this->assertSame(1, LedgerEntry::query()->where('company_id', $buyer->company_id)->where('type', 'payment')->count());
        $this->assertSame('paid', Order::query()->where('order_number', $number)->first()->payment_status->value);

        // Paid before invoicing → confirmation issues the invoice already settled.
        $this->actingAsUser($this->admin);
        $this->patchJson("/api/v1/admin/orders/{$number}/status", ['status' => 'confirmed'])->assertOk()
            ->assertJsonPath('data.invoice.status.value', 'paid');
        $this->assertSame('0.00', $this->balance($buyer->company));
    }

    public function test_mock_checkout_page_requires_valid_signature(): void
    {
        $buyer = $this->buyer();
        $number = $this->order($buyer, 'gateway', confirm: false);
        $this->actingAsUser($buyer);
        $redirect = $this->postJson("/api/v1/buyer/orders/{$number}/pay-online")->json('redirect_url');

        $this->get($redirect)->assertOk()->assertSee('SANDBOX');
        $this->get($redirect.'x')->assertStatus(403);
    }

    public function test_statement_running_balance_and_adjustments(): void
    {
        $buyer = $this->buyer();
        $number = $this->order($buyer);

        $this->actingAsUser($this->admin);
        $this->postJson("/api/v1/admin/companies/{$buyer->company->public_id}/ledger/adjustments", [
            'direction' => 'credit', 'amount' => '860.00', 'note' => 'Damaged goods allowance',
        ])->assertCreated()->assertJsonPath('outstanding_balance', '34000.00');

        $invoice = Order::query()->where('order_number', $number)->first()->invoice;
        $this->postJson("/api/v1/admin/companies/{$buyer->company->public_id}/credit-notes", [
            'amount' => '1000.00', 'reason' => 'Price correction', 'invoice_number' => $invoice->invoice_number,
        ])->assertCreated();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);

        $this->actingAsUser($buyer);
        $this->withHeader('Accept-Language', 'bn')->getJson('/api/v1/buyer/ledger')
            ->assertOk()
            ->assertJsonPath('opening_balance', '0.00')
            ->assertJsonPath('closing_balance', '33000.00')
            ->assertJsonPath('total_debit', '34860.00')
            ->assertJsonPath('total_credit', '1860.00')
            ->assertJsonCount(3, 'entries')
            ->assertJsonPath('entries.2.balance', '33000.00')
            ->assertJsonPath('entries.0.type.label', 'চালান');

        $this->get('/api/v1/buyer/ledger/export')->assertOk()->assertDownload();
    }

    public function test_buyer_cannot_adjust_ledger_or_see_other_company_finance(): void
    {
        $buyerA = $this->buyer();
        $number = $this->order($buyerA);
        $invoice = Order::query()->where('order_number', $number)->first()->invoice;

        $this->actingAsUser($this->buyer());
        $this->getJson("/api/v1/buyer/invoices/{$invoice->invoice_number}")->assertStatus(403);
        $this->get("/api/v1/invoices/{$invoice->invoice_number}/pdf")->assertStatus(403);
        $this->getJson("/api/v1/admin/companies/{$buyerA->company->public_id}/ledger")->assertStatus(403);
        $this->getJson('/api/v1/buyer/ledger')->assertOk()->assertJsonCount(0, 'entries');

        $this->actingAsUser($this->staff(Role::SalesManager));
        $this->postJson("/api/v1/admin/companies/{$buyerA->company->public_id}/ledger/adjustments", [
            'direction' => 'credit', 'amount' => '100', 'note' => 'unauthorised attempt',
        ])->assertStatus(403);
    }

    public function test_overdue_invoices_block_new_credit_orders_and_trigger_reminders(): void
    {
        $buyer = $this->buyer();
        $number = $this->order($buyer);
        Invoice::query()->where('order_id', Order::query()->where('order_number', $number)->value('id'))
            ->update(['due_date' => today()->subDays(7)->toDateString()]);

        $this->fillCart($buyer, ['OIL-SOY-5L' => 8]);
        $this->actingAsUser($buyer);
        $this->postJson('/api/v1/buyer/orders', $this->checkoutPayload($buyer))->assertStatus(422)->assertJsonPath('code', 'credit_overdue_invoices');

        $this->artisan('invoices:remind-overdue')->assertSuccessful();
        Notification::assertSentTo($buyer, InvoiceOverdueNotification::class);
    }
}
