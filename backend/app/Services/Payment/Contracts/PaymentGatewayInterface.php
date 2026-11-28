<?php

namespace App\Services\Payment\Contracts;

use App\Models\Payment;
use App\Services\Payment\GatewayInitiation;
use App\Services\Payment\GatewayResult;
use Illuminate\Http\Request;

/**
 * Contract every online payment driver implements (SSLCommerz, bKash, Nagad, Stripe, mock).
 *
 * Security requirements for implementations:
 *  - verifyCallback() MUST authenticate the request (signature / server-to-server validation API)
 *    and MUST return the amount the gateway actually captured; never trust client-supplied amounts.
 *  - Secrets are read from config/env only.
 */
interface PaymentGatewayInterface
{
    /** Driver key, e.g. "sslcommerz". Stored on payments.gateway. */
    public function name(): string;

    public function isEnabled(): bool;

    /** Creates a gateway session for a pending payment and returns where to send the buyer. */
    public function initiate(Payment $payment, array $context = []): GatewayInitiation;

    /** Authenticates and parses a callback/IPN/webhook. */
    public function verifyCallback(Request $request): GatewayResult;

    /** Full or partial refund of a captured payment. */
    public function refund(Payment $payment, string $amount): GatewayResult;
}
