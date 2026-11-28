<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\GatewayInitiation;
use App\Services\Payment\GatewayResult;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sandbox driver with the same shape as a real gateway: the buyer is redirected to a hosted
 * "checkout" (signed URL), which then calls back with an HMAC-signed payload. Disabled in
 * production unless PAYMENT_MOCK_ENABLED=true.
 */
class MockGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly array $config = []) {}

    public function name(): string
    {
        return 'mock';
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function initiate(Payment $payment, array $context = []): GatewayInitiation
    {
        $transactionId = 'MOCK-'.Str::upper(Str::random(16));

        $url = URL::temporarySignedRoute('payments.mock.checkout', now()->addMinutes(30), [
            'payment' => $payment->reference,
            'txn' => $transactionId,
        ]);

        return new GatewayInitiation($url, $transactionId, ['sandbox' => true]);
    }

    /** Builds the signed callback body the hosted page posts back (also used by tests). */
    public function signedPayload(Payment $payment, string $transactionId, string $status): array
    {
        $payload = [
            'payment_reference' => $payment->reference,
            'transaction_id' => $transactionId,
            'status' => $status,
            'amount' => Money::format(Money::of((string) $payment->amount)),
            'currency' => $payment->currency,
        ];

        return $payload + ['signature' => $this->sign($payload)];
    }

    public function verifyCallback(Request $request): GatewayResult
    {
        $payload = $request->only(['payment_reference', 'transaction_id', 'status', 'amount', 'currency']);
        $signature = (string) $request->input('signature');

        if (count($payload) !== 5 || ! hash_equals($this->sign($payload), $signature)) {
            throw new HttpException(400, 'Invalid gateway signature.');
        }

        return new GatewayResult(
            status: in_array($payload['status'], [GatewayResult::SUCCESS, GatewayResult::FAILED, GatewayResult::CANCELLED], true) ? $payload['status'] : GatewayResult::FAILED,
            paymentReference: (string) $payload['payment_reference'],
            transactionId: (string) $payload['transaction_id'],
            amount: (string) $payload['amount'],
            currency: (string) $payload['currency'],
            raw: $payload,
        );
    }

    public function refund(Payment $payment, string $amount): GatewayResult
    {
        return new GatewayResult(GatewayResult::SUCCESS, $payment->reference, (string) $payment->gateway_transaction_id, $amount, $payment->currency, ['refund' => true]);
    }

    private function sign(array $payload): string
    {
        ksort($payload);

        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), (string) ($this->config['secret'] ?? ''));
    }
}
