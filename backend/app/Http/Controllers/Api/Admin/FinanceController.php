<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreditNoteRequest;
use App\Http\Requests\Admin\LedgerAdjustmentRequest;
use App\Http\Resources\CreditNoteResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\LedgerEntryResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Finance\CreditLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function __construct(private readonly CreditLedgerService $ledger) {}

    public function invoices(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['unpaid', 'partially_paid', 'paid', 'void', 'outstanding', 'overdue'])],
            'company_id' => ['nullable', 'string', 'size:26'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Invoice::query()
            ->whereHas('company', fn ($q) => $q->visibleTo($request->user()))
            ->when($validated['company_id'] ?? null, fn ($q, $id) => $q->whereHas('company', fn ($c) => $c->where('public_id', $id)))
            ->with(['company:id,public_id,name', 'order:id,order_number']);

        match ($validated['status'] ?? null) {
            null => null,
            'outstanding' => $query->outstanding(),
            'overdue' => $query->overdue(),
            default => $query->where('status', $validated['status']),
        };

        return InvoiceResource::collection($query->orderBy('due_date')->paginate((int) ($validated['per_page'] ?? 25)));
    }

    public function adjust(LedgerAdjustmentRequest $request, Company $company): JsonResponse
    {
        Gate::authorize('adjustLedger', $company);

        $entry = $this->ledger->adjust($company, $request->validated('direction'), (string) $request->validated('amount'), $request->validated('note'), $request->user());

        return (new LedgerEntryResource($entry))
            ->additional(['message' => __('messages.ledger.adjusted'), 'outstanding_balance' => $company->fresh()->outstanding_balance])
            ->response()
            ->setStatusCode(201);
    }

    public function creditNote(CreditNoteRequest $request, Company $company): JsonResponse
    {
        Gate::authorize('adjustLedger', $company);

        $invoice = $request->filled('invoice_number')
            ? Invoice::query()->where('company_id', $company->id)->where('invoice_number', $request->validated('invoice_number'))->firstOrFail()
            : null;

        $note = $this->ledger->issueCreditNote($company, (string) $request->validated('amount'), $request->validated('reason'), $request->user(), $invoice);

        return (new CreditNoteResource($note->load(['invoice:id,invoice_number', 'issuer:id,name'])))
            ->additional(['message' => __('messages.ledger.credit_note_issued', ['number' => $note->credit_note_number])])
            ->response()
            ->setStatusCode(201);
    }

    public function creditNotes(Company $company): AnonymousResourceCollection
    {
        Gate::authorize('viewLedger', $company);

        return CreditNoteResource::collection($company->creditNotes()->with(['invoice:id,invoice_number', 'issuer:id,name'])->latest('id')->paginate(25));
    }
}
