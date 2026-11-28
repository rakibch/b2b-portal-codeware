<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Bilingual;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Append-only receivables ledger per buyer company.
 *
 * Invariant: companies.outstanding_balance == balance of the company's latest ledger entry.
 * Every post happens inside a transaction holding a row lock on the company, so running
 * balances can never interleave between concurrent writers.
 *
 * debit  = buyer owes more (invoice, debit adjustment)
 * credit = buyer owes less (payment, credit note, invoice reversal, credit adjustment)
 */
class CreditLedgerService
{
    /** Must be called inside DB::transaction(). Lock order everywhere: company → orders → products. */
    public function lockCompany(Company|int $company): Company
    {
        $this->assertInTransaction();

        return Company::query()->whereKey($company instanceof Company ? $company->id : $company)->lockForUpdate()->firstOrFail();
    }

    public function post(
        Company $lockedCompany,
        LedgerEntryType $type,
        string $debit,
        string $credit,
        array $description,
        ?Model $source = null,
        ?string $reference = null,
        ?User $actor = null,
        ?Carbon $date = null,
    ): LedgerEntry {
        $this->assertInTransaction();

        if (Money::compare($debit, '0') < 0 || Money::compare($credit, '0') < 0 || (! Money::isPositive($debit) && ! Money::isPositive($credit))) {
            throw new LogicException('Ledger entry must have a positive debit or credit.');
        }

        $balance = Money::sub(Money::add((string) $lockedCompany->outstanding_balance, $debit), $credit);

        $entry = LedgerEntry::query()->forceCreate([
            'company_id' => $lockedCompany->id,
            'entry_date' => ($date ?? now())->toDateString(),
            'type' => $type,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'reference' => $reference,
            'description' => $description,
            'debit' => Money::format(Money::of($debit)),
            'credit' => Money::format(Money::of($credit)),
            'balance' => $balance,
            'created_by' => $actor?->id,
        ]);

        $lockedCompany->forceFill(['outstanding_balance' => $balance])->save();

        return $entry;
    }

    public function recordInvoice(Company $lockedCompany, Invoice $invoice, ?User $actor): LedgerEntry
    {
        return $this->post(
            $lockedCompany,
            LedgerEntryType::Invoice,
            (string) $invoice->grand_total,
            '0',
            Bilingual::trans('messages.ledger.invoice', ['number' => $invoice->invoice_number, 'order' => $invoice->order->order_number]),
            $invoice,
            $invoice->invoice_number,
            $actor,
        );
    }

    public function reverseInvoice(Company $lockedCompany, Invoice $invoice, string $reason, ?User $actor): LedgerEntry
    {
        return $this->post(
            $lockedCompany,
            LedgerEntryType::Reversal,
            '0',
            (string) $invoice->grand_total,
            Bilingual::trans('messages.ledger.invoice_void', ['number' => $invoice->invoice_number, 'reason' => $reason]),
            $invoice,
            $invoice->invoice_number,
            $actor,
        );
    }

    public function recordPayment(Company $lockedCompany, Payment $payment, ?User $actor): LedgerEntry
    {
        return $this->post(
            $lockedCompany,
            LedgerEntryType::Payment,
            '0',
            (string) $payment->amount,
            Bilingual::trans('messages.ledger.payment', [
                'reference' => $payment->reference,
                'method' => Bilingual::trans('enums.payment_record_method.'.$payment->method->value),
            ]),
            $payment,
            $payment->reference,
            $actor,
        );
    }

    public function recordCreditNote(Company $lockedCompany, CreditNote $note, ?User $actor): LedgerEntry
    {
        return $this->post(
            $lockedCompany,
            LedgerEntryType::CreditNote,
            '0',
            (string) $note->amount,
            Bilingual::trans('messages.ledger.credit_note', ['number' => $note->credit_note_number, 'reason' => $note->reason]),
            $note,
            $note->credit_note_number,
            $actor,
        );
    }

    /** Manual correction (ledger.adjust). Direction: 'debit' raises the balance, 'credit' lowers it. */
    public function adjust(Company $company, string $direction, string $amount, string $note, User $actor): LedgerEntry
    {
        return DB::transaction(function () use ($company, $direction, $amount, $note, $actor): LedgerEntry {
            $locked = $this->lockCompany($company);

            $entry = $this->post(
                $locked,
                LedgerEntryType::Adjustment,
                $direction === 'debit' ? $amount : '0',
                $direction === 'credit' ? $amount : '0',
                Bilingual::trans('messages.ledger.adjustment', ['note' => $note]),
                null,
                'ADJ-'.now()->format('YmdHis'),
                $actor,
            );

            AuditLog::record('ledger.adjusted', $locked, [], ['direction' => $direction, 'amount' => $amount, 'note' => $note]);

            return $entry;
        });
    }

    /** Credit note against a company (optionally an invoice) — reduces receivable. */
    public function issueCreditNote(Company $company, string $amount, string $reason, User $actor, ?Invoice $invoice = null): CreditNote
    {
        return DB::transaction(function () use ($company, $amount, $reason, $actor, $invoice): CreditNote {
            $locked = $this->lockCompany($company);

            if ($invoice !== null) {
                $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                if ($invoice->company_id !== $locked->id || $invoice->status === InvoiceStatus::Void) {
                    throw BusinessRuleException::make('credit_note_invalid_invoice');
                }

                if (Money::compare($amount, $invoice->balanceDue()) > 0) {
                    throw BusinessRuleException::make('credit_note_exceeds_balance', ['balance' => $invoice->balanceDue()]);
                }
            }

            $note = new CreditNote(['reason' => $reason]);
            $note->forceFill([
                'credit_note_number' => DocumentSequence::next('credit_note'),
                'company_id' => $locked->id,
                'invoice_id' => $invoice?->id,
                'order_id' => $invoice?->order_id,
                'amount' => Money::format(Money::of($amount)),
                'issued_by' => $actor->id,
            ])->save();

            $this->recordCreditNote($locked, $note, $actor);

            if ($invoice !== null) {
                $invoice->forceFill(['amount_credited' => Money::add((string) $invoice->amount_credited, $amount)])->save();
                app(InvoiceService::class)->refreshStatus($invoice);
            }

            AuditLog::record('ledger.credit_note_issued', $locked, [], ['number' => $note->credit_note_number, 'amount' => $amount]);

            return $note;
        });
    }

    /**
     * Credit exposure = ledger balance + open credit orders not yet invoiced.
     * Counting uninvoiced orders prevents a buyer from stacking pending orders past the limit.
     */
    public function exposure(Company $lockedCompany): string
    {
        $uninvoiced = (string) Order::query()
            ->where('company_id', $lockedCompany->id)
            ->where('payment_method', PaymentMethod::Credit->value)
            ->whereIn('status', [OrderStatus::Pending->value])
            ->whereDoesntHave('invoice')
            ->sum('grand_total');

        return Money::add((string) $lockedCompany->outstanding_balance, $uninvoiced);
    }

    public function availableCredit(Company $lockedCompany): string
    {
        if (! $lockedCompany->credit_enabled || $lockedCompany->credit_on_hold) {
            return '0.00';
        }

        return Money::max(Money::sub((string) $lockedCompany->credit_limit, $this->exposure($lockedCompany)), '0');
    }

    public function assertCreditAvailable(Company $lockedCompany, string $amount): void
    {
        if (! $lockedCompany->credit_enabled) {
            throw BusinessRuleException::make('credit_not_enabled');
        }

        if ($lockedCompany->credit_on_hold) {
            throw BusinessRuleException::make('credit_on_hold');
        }

        if ($lockedCompany->overdueInvoices()->exists()) {
            throw BusinessRuleException::make('credit_overdue_invoices');
        }

        $available = $this->availableCredit($lockedCompany);

        if (Money::compare($amount, $available) > 0) {
            throw BusinessRuleException::make('credit_limit_exceeded', [
                'available' => $available,
                'amount' => Money::format(Money::of($amount)),
            ], 422, ['available_credit' => $available, 'order_total' => Money::format(Money::of($amount))]);
        }
    }

    /**
     * Statement of account for a period: opening balance, chronological entries, closing balance.
     *
     * @return array{opening_balance: string, closing_balance: string, total_debit: string, total_credit: string, entries: Collection<int, LedgerEntry>}
     */
    public function statement(Company $company, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subDays(90)->startOfDay();
        $to ??= now()->endOfDay();

        $opening = LedgerEntry::query()
            ->where('company_id', $company->id)
            ->whereDate('entry_date', '<', $from->toDateString())
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->value('balance') ?? '0.00';

        $entries = LedgerEntry::query()
            ->where('company_id', $company->id)
            ->whereDate('entry_date', '>=', $from->toDateString())
            ->whereDate('entry_date', '<=', $to->toDateString())
            ->chronological()
            ->get();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'opening_balance' => Money::format(Money::of((string) $opening)),
            'closing_balance' => Money::format(Money::of((string) ($entries->last()?->balance ?? $opening))),
            'total_debit' => Money::add('0', ...$entries->pluck('debit')->map(fn ($v) => (string) $v)->all()),
            'total_credit' => Money::add('0', ...$entries->pluck('credit')->map(fn ($v) => (string) $v)->all()),
            'entries' => $entries,
        ];
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Ledger operations must run inside a database transaction.');
        }
    }
}
