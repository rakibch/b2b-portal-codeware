<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\GatewayManager;
use App\Services\Payment\Gateways\MockGateway;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public gateway endpoints. Authenticity comes from the driver's verifyCallback() (signature /
 * server-side validation), never from the session — gateways call these server-to-server.
 */
class GatewayController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly GatewayManager $gateways,
    ) {}

    /** IPN / webhook / callback. */
    public function callback(Request $request, string $gateway): JsonResponse
    {
        abort_unless($this->gateways->has($gateway), 404);

        $payment = $this->payments->handleGatewayCallback($gateway, $request);

        return response()->json(['reference' => $payment->reference, 'status' => $payment->status->value]);
    }

    /** Sandbox hosted checkout page (signed URL, mock driver only). */
    public function mockCheckout(Request $request, string $payment): Response
    {
        $driver = $this->gateways->driver('mock');
        abort_unless($driver instanceof MockGateway && $driver->isEnabled(), 404);

        $record = Payment::query()->where('reference', $payment)->where('gateway', 'mock')->firstOrFail();
        abort_unless(hash_equals((string) $record->gateway_transaction_id, (string) $request->query('txn')), 404);

        return response()->view('payments.mock-checkout', [
            'payment' => $record,
            'success' => $driver->signedPayload($record, $record->gateway_transaction_id, 'success'),
            'failed' => $driver->signedPayload($record, $record->gateway_transaction_id, 'failed'),
            'callbackUrl' => route('payments.callback', ['gateway' => 'mock']),
            'returnUrl' => str_replace('{order}', (string) $record->order?->order_number, (string) config('payments.return_url')),
        ]);
    }

    /** Browser return after the hosted page (sandbox): process then bounce to the SPA. */
    public function mockReturn(Request $request): RedirectResponse
    {
        $payment = $this->payments->handleGatewayCallback('mock', $request);

        return redirect()->away(str_replace('{order}', (string) $payment->order?->order_number, (string) config('payments.return_url')).'?status='.$payment->status->value);
    }
}
