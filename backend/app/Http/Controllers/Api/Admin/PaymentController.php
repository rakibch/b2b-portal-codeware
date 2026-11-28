<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentRecordMethod;
use App\Enums\PaymentRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReasonRequest;
use App\Http\Requests\Admin\RecordCashRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Payment verification desk. */
class PaymentController extends Controller
{
    private const RELATIONS = ['company:id,public_id,name', 'order:id,order_number', 'submitter:id,name', 'verifier:id,name', 'allocations.invoice:id,invoice_number'];

    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentRecordStatus::class)],
            'method' => ['nullable', Rule::enum(PaymentRecordMethod::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $payments = Payment::query()
            ->whereHas('company', fn ($q) => $q->visibleTo($request->user()))
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($validated['method'] ?? null, fn ($q, $m) => $q->where('method', $m))
            ->with(self::RELATIONS)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [PaymentRecordStatus::Pending->value])
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return PaymentResource::collection($payments);
    }

    public function show(Payment $payment): PaymentResource
    {
        Gate::authorize('view', $payment);

        return new PaymentResource($payment->load(self::RELATIONS));
    }

    public function verify(Request $request, Payment $payment): PaymentResource
    {
        Gate::authorize('verify', $payment);

        $payment = $this->payments->verify($payment, $request->user());

        return (new PaymentResource($payment->load(self::RELATIONS)))->additional(['message' => __('messages.payments.verified', ['reference' => $payment->reference])]);
    }

    public function reject(ReasonRequest $request, Payment $payment): PaymentResource
    {
        Gate::authorize('verify', $payment);

        $payment = $this->payments->reject($payment, $request->user(), $request->validated('reason'));

        return (new PaymentResource($payment->load(self::RELATIONS)))->additional(['message' => __('messages.payments.rejected', ['reference' => $payment->reference])]);
    }

    /** Cash collected on delivery. */
    public function recordCash(RecordCashRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $payment = $this->payments->recordCash($order, $request->user(), (string) $request->validated('amount'), $request->validated('notes'));

        return (new PaymentResource($payment->load(self::RELATIONS)))
            ->additional(['message' => __('messages.payments.cash_recorded', ['reference' => $payment->reference])])
            ->response()
            ->setStatusCode(201);
    }
}
