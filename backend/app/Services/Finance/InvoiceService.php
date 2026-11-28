<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

class InvoiceService
{
    public function __construct(private readonly CreditLedgerService $ledger) {}

    /**
     * Issues the tax invoice for a confirmed order (inside the caller's transaction, company locked),
     * posts the ledger debit and applies any verified-but-unallocated payments for that order.
     */
    public function issueForOrder(Company $lockedCompany, Order $order, ?User $actor): Invoice
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Invoices must be issued inside a transaction.');
        }

        if ($order->invoice()->exists()) {
            return $order->invoice;
        }

        $terms = $order->payment_method === PaymentMethod::Credit ? (int) $lockedCompany->payment_terms_days : 0;

        $invoice = new Invoice;
        $invoice->forceFill([
            'invoice_number' => DocumentSequence::next('invoice'),
            'order_id' => $order->id,
            'company_id' => $order->company_id,
            'issue_date' => today()->toDateString(),
            'due_date' => today()->addDays($terms)->toDateString(),
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'vat_total' => $order->vat_total,
            'shipping_total' => $order->shipping_total,
            'grand_total' => $order->grand_total,
            'amount_paid' => '0.00',
            'amount_credited' => '0.00',
            'status' => InvoiceStatus::Unpaid,
        ])->save();

        $invoice->setRelation('order', $order);
        $this->ledger->recordInvoice($lockedCompany, $invoice, $actor);

        $unallocated = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentRecordStatus::Verified->value)
            ->lockForUpdate()
            ->get();

        foreach ($unallocated as $payment) {
            $remaining = $this->unallocatedAmount($payment);

            if (Money::isPositive($remaining)) {
                $this->allocate($payment, $invoice, Money::compare($remaining, $invoice->balanceDue()) > 0 ? $invoice->balanceDue() : $remaining);
            }
        }

        return $invoice->refresh();
    }

    /** Applies part of a payment to an invoice (caller holds locks). */
    public function allocate(Payment $payment, Invoice $invoice, string $amount): ?PaymentAllocation
    {
        if (! Money::isPositive($amount)) {
            return null;
        }

        $allocation = PaymentAllocation::query()->where('payment_id', $payment->id)->where('invoice_id', $invoice->id)->first();

        if ($allocation) {
            $allocation->update(['amount' => Money::add((string) $allocation->amount, $amount)]);
        } else {
            $allocation = $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $amount]);
        }

        $invoice->forceFill(['amount_paid' => Money::add((string) $invoice->amount_paid, $amount)])->save();
        $this->refreshStatus($invoice);

        return $allocation;
    }

    public function unallocatedAmount(Payment $payment): string
    {
        return Money::sub((string) $payment->amount, (string) $payment->allocations()->sum('amount'));
    }

    public function refreshStatus(Invoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Void) {
            return;
        }

        $settled = Money::add((string) $invoice->amount_paid, (string) $invoice->amount_credited);

        $status = match (true) {
            Money::compare($settled, (string) $invoice->grand_total) >= 0 => InvoiceStatus::Paid,
            Money::isPositive($settled) => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Unpaid,
        };

        $invoice->forceFill([
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::Paid ? ($invoice->paid_at ?? now()) : null,
        ])->save();

        $invoice->loadMissing('order');
        $this->syncOrderPayment($invoice->order);
    }

    /** Voids an invoice (order cancellation) and reverses its ledger debit. */
    public function void(Company $lockedCompany, Invoice $invoice, string $reason, ?User $actor): void
    {
        if ($invoice->status === InvoiceStatus::Void) {
            return;
        }

        $invoice->loadMissing('order');
        $this->ledger->reverseInvoice($lockedCompany, $invoice, $reason, $actor);

        // Payments previously applied become unallocated credit on account.
        $invoice->allocations()->delete();
        $invoice->forceFill(['status' => InvoiceStatus::Void, 'voided_at' => now(), 'amount_paid' => '0.00'])->save();
    }

    /** Order.amount_paid / payment_status derived from invoice (or verified payments before invoicing). */
    public function syncOrderPayment(Order $order): void
    {
        $invoice = $order->invoice()->first();

        $paid = $invoice && $invoice->status !== InvoiceStatus::Void
            ? Money::add((string) $invoice->amount_paid, (string) $invoice->amount_credited)
            : (string) Payment::query()->where('order_id', $order->id)->where('status', PaymentRecordStatus::Verified->value)->sum('amount');

        $hasPending = Payment::query()->where('order_id', $order->id)->where('status', PaymentRecordStatus::Pending->value)->exists();

        $status = match (true) {
            Money::compare($paid, (string) $order->grand_total) >= 0 => OrderPaymentStatus::Paid,
            Money::isPositive($paid) => OrderPaymentStatus::PartiallyPaid,
            $hasPending => OrderPaymentStatus::PendingVerification,
            default => OrderPaymentStatus::Unpaid,
        };

        $order->forceFill(['amount_paid' => Money::format(Money::of($paid)), 'payment_status' => $status])->save();
    }
}
