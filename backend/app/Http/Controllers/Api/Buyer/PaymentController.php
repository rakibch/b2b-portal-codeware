<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\SubmitPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('payments.submit'), 403);

        $payments = Payment::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['order:id,order_number', 'allocations.invoice:id,invoice_number'])
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        return PaymentResource::collection($payments);
    }

    public function store(SubmitPaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->submitManual($request->user(), $request->safe()->except('slip'), $request->file('slip'));

        return (new PaymentResource($payment->load('order:id,order_number')))
            ->additional(['message' => __('messages.payments.submitted', ['reference' => $payment->reference])])
            ->response()
            ->setStatusCode(201);
    }

    /** Starts an online gateway payment; the SPA redirects the browser to `redirect_url`. */
    public function payOnline(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('pay', $order);

        $result = $this->payments->initiateGateway($order, $request->user());

        return response()->json([
            'payment' => new PaymentResource($result['payment']),
            'redirect_url' => $result['redirect_url'],
        ], 201);
    }
}
