<?php

namespace App\Services\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentSubmittedNotification;
use App\Notifications\PaymentVerifiedNotification;
use App\Services\Finance\CreditLedgerService;
use App\Services\Finance\InvoiceService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Payments: manual (bank transfer / cheque with slip → admin verification), cash collected on
 * delivery, and online gateways. Verification is the ONLY place a payment hits the ledger.
 */
class PaymentService
{
    public function __construct(
        private readonly CreditLedgerService $ledger,
        private readonly InvoiceService $invoices,
        private readonly GatewayManager $gateways,
    ) {}

    /** Buyer submits bank transfer / cheque evidence for an order or on account. */
    public function submitManual(User $buyer, array $data, ?UploadedFile $slip): Payment
    {
        $company = $buyer->company;
        $order = ! empty($data['order_number']) ? $this->companyOrder($company, (string) $data['order_number']) : null;
        $disk = (string) config('b2b.payments.slip_disk');
        $slipPath = null;

        if ($slip !== null) {
            $slipPath = $slip->storeAs("payment-slips/{$company->public_id}", Str::uuid()->toString().'.'.($slip->guessExtension() ?: 'bin'), $disk);
        }

        try {
            $payment = DB::transaction(function () use ($buyer, $company, $order, $data, $disk, $slipPath): Payment {
                $payment = new Payment(array_intersect_key($data, array_flip(['transaction_ref', 'bank_name', 'cheque_number', 'deposit_date', 'notes'])));
                $payment->forceFill([
                    'reference' => DocumentSequence::next('payment'),
                    'company_id' => $company->id,
                    'order_id' => $order?->id,
                    'method' => PaymentRecordMethod::from($data['method']),
                    'amount' => Money::format(Money::of((string) $data['amount'])),
                    'currency' => config('b2b.currency.code'),
                    'status' => PaymentRecordStatus::Pending,
                    'slip_disk' => $slipPath ? $disk : null,
                    'slip_path' => $slipPath,
                    'submitted_by' => $buyer->id,
                ])->save();

                if ($order !== null) {
                    $this->invoices->syncOrderPayment($order);
                }

                AuditLog::record('payment.submitted', $payment, [], ['amount' => $payment->amount, 'method' => $payment->method->value]);

                return $payment;
            });
        } catch (Throwable $e) {
            if ($slipPath) {
                Storage::disk($disk)->delete($slipPath);
            }

            throw $e;
        }

        $verifiers = User::query()->staff()->active()->permission('payments.verify')->get();
        Notification::send($verifiers, new PaymentSubmittedNotification($payment));

        return $payment;
    }

    /** Admin verification: credits the ledger and allocates to invoices (order first, then FIFO). */
    public function verify(Payment $payment, ?User $verifier, ?array $gatewayPayload = null): Payment
    {
        $payment = DB::transaction(function () use ($payment, $verifier, $gatewayPayload): Payment {
            $company = $this->ledger->lockCompany($payment->company_id);
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentRecordStatus::Verified) {
                return $payment; // idempotent (gateway retries)
            }

            if ($payment->status !== PaymentRecordStatus::Pending) {
                throw BusinessRuleException::make('payment_not_pending');
            }

            $payment->forceFill([
                'status' => PaymentRecordStatus::Verified,
                'verified_by' => $verifier?->id,
                'verified_at' => now(),
                'gateway_payload' => $gatewayPayload ?? $payment->gateway_payload,
            ])->save();

            $this->ledger->recordPayment($company, $payment, $verifier);
            $this->allocate($company, $payment);

            if ($payment->order_id !== null && $payment->order !== null) {
                $this->invoices->syncOrderPayment($payment->order);
            }

            AuditLog::record('payment.verified', $payment, [], ['amount' => $payment->amount, 'by' => $verifier?->id]);

            return $payment;
        });

        $this->notifyBuyer($payment, new PaymentVerifiedNotification($payment));

        return $payment;
    }

    public function reject(Payment $payment, User $verifier, string $reason): Payment
    {
        $payment = DB::transaction(function () use ($payment, $verifier, $reason): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== PaymentRecordStatus::Pending) {
                throw BusinessRuleException::make('payment_not_pending');
            }

            $payment->forceFill([
                'status' => PaymentRecordStatus::Rejected,
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            if ($payment->order !== null) {
                $this->invoices->syncOrderPayment($payment->order);
            }

            AuditLog::record('payment.rejected', $payment, [], ['reason' => $reason]);

            return $payment;
        });

        $this->notifyBuyer($payment, new PaymentRejectedNotification($payment));

        return $payment;
    }

    /** Cash collected at delivery (COD) — recorded and verified by staff in one step. */
    public function recordCash(Order $order, User $staff, string $amount, ?string $notes): Payment
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw BusinessRuleException::make('order_not_payable');
        }

        $payment = DB::transaction(function () use ($order, $staff, $amount, $notes): Payment {
            $payment = new Payment(['notes' => $notes]);
            $payment->forceFill([
                'reference' => DocumentSequence::next('payment'),
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'method' => PaymentRecordMethod::Cash,
                'amount' => Money::format(Money::of($amount)),
                'currency' => $order->currency,
                'status' => PaymentRecordStatus::Pending,
                'submitted_by' => $staff->id,
            ])->save();

            return $payment;
        });

        return $this->verify($payment, $staff);
    }

    /** Starts an online payment for the outstanding balance of a gateway order. */
    public function initiateGateway(Order $order, User $buyer, ?string $gatewayName = null): array
    {
        if ($order->payment_method !== PaymentMethod::Gateway || $order->status === OrderStatus::Cancelled) {
            throw BusinessRuleException::make('order_not_payable');
        }

        $driver = $this->gateways->driver($gatewayName);

        if (! $driver->isEnabled()) {
            throw BusinessRuleException::make('gateway_unavailable');
        }

        $due = Money::sub((string) $order->grand_total, (string) $order->amount_paid);

        if (! Money::isPositive($due)) {
            throw BusinessRuleException::make('order_already_paid');
        }

        [$payment, $initiation] = DB::transaction(function () use ($order, $buyer, $driver, $due): array {
            // Supersede earlier unfinished attempts so only one gateway session can settle.
            Payment::query()
                ->where('order_id', $order->id)
                ->where('method', PaymentRecordMethod::Gateway->value)
                ->where('status', PaymentRecordStatus::Pending->value)
                ->update(['status' => PaymentRecordStatus::Failed->value, 'rejection_reason' => 'superseded', 'updated_at' => now()]);

            $payment = new Payment;
            $payment->forceFill([
                'reference' => DocumentSequence::next('payment'),
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'method' => PaymentRecordMethod::Gateway,
                'amount' => $due,
                'currency' => $order->currency,
                'status' => PaymentRecordStatus::Pending,
                'gateway' => $driver->name(),
                'submitted_by' => $buyer->id,
            ])->save();

            $initiation = $driver->initiate($payment, ['order' => $order->order_number]);
            $payment->forceFill(['gateway_transaction_id' => $initiation->transactionId, 'gateway_payload' => $initiation->payload])->save();

            return [$payment, $initiation];
        });

        return ['payment' => $payment, 'redirect_url' => $initiation->redirectUrl];
    }

    /** Authenticated gateway callback / IPN. Idempotent and amount-checked. */
    public function handleGatewayCallback(string $gatewayName, Request $request): Payment
    {
        $driver = $this->gateways->driver($gatewayName);
        $result = $driver->verifyCallback($request);

        $payment = Payment::query()
            ->where('reference', $result->paymentReference)
            ->where('gateway', $driver->name())
            ->where('gateway_transaction_id', $result->transactionId)
            ->firstOrFail();

        if ($payment->status === PaymentRecordStatus::Verified) {
            return $payment;
        }

        if (! $result->isSuccessful()) {
            $payment->forceFill(['status' => PaymentRecordStatus::Failed, 'gateway_payload' => $result->raw])->save();
            AuditLog::record('payment.gateway_failed', $payment, [], ['status' => $result->status]);

            return $payment;
        }

        if (Money::compare($result->amount, (string) $payment->amount) !== 0 || $result->currency !== $payment->currency) {
            $payment->forceFill(['status' => PaymentRecordStatus::Failed, 'rejection_reason' => 'amount_mismatch', 'gateway_payload' => $result->raw])->save();
            AuditLog::record('payment.gateway_amount_mismatch', $payment, [], ['expected' => $payment->amount, 'received' => $result->amount]);

            throw BusinessRuleException::make('payment_amount_mismatch');
        }

        return $this->verify($payment, null, $result->raw);
    }

    /** Applies the verified payment: its order's invoice first, then the oldest open invoices. */
    private function allocate(Company $lockedCompany, Payment $payment): void
    {
        $remaining = $this->invoices->unallocatedAmount($payment);

        $query = Invoice::query()
            ->where('company_id', $lockedCompany->id)
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::PartiallyPaid->value]);

        $ordered = $payment->order_id
            ? $query->orderByRaw('CASE WHEN order_id = ? THEN 0 ELSE 1 END', [$payment->order_id])->orderBy('due_date')->orderBy('id')
            : $query->orderBy('due_date')->orderBy('id');

        // An order-linked payment made before the order is invoiced stays unallocated until invoicing.
        if ($payment->order_id !== null && ! Invoice::query()->where('order_id', $payment->order_id)->exists()) {
            return;
        }

        foreach ($ordered->lockForUpdate()->get() as $invoice) {
            if (! Money::isPositive($remaining)) {
                break;
            }

            $apply = Money::compare($remaining, $invoice->balanceDue()) > 0 ? $invoice->balanceDue() : $remaining;
            $this->invoices->allocate($payment, $invoice, $apply);
            $remaining = Money::sub($remaining, $apply);
        }
    }

    /** Tenant-scoped lookup: an order number from another company simply isn't found. */
    private function companyOrder(Company $company, string $orderNumber): Order
    {
        $order = Order::query()->where('company_id', $company->id)->where('order_number', $orderNumber)->first();

        if ($order === null || $order->status === OrderStatus::Cancelled) {
            throw BusinessRuleException::make('order_not_payable');
        }

        return $order;
    }

    private function notifyBuyer(Payment $payment, object $notification): void
    {
        $recipients = $payment->submitted_by
            ? User::query()->whereKey($payment->submitted_by)->buyers()->active()->get()
            : collect();

        if ($recipients->isEmpty()) {
            $recipients = User::query()->where('company_id', $payment->company_id)->active()->role(Role::BuyerAdmin->value)->get();
        }

        Notification::send($recipients, $notification);
    }
}
