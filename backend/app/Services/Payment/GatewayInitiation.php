<?php

namespace App\Services\Payment;

final readonly class GatewayInitiation
{
    public function __construct(
        public string $redirectUrl,
        public string $transactionId,
        public array $payload = [],
    ) {}
}
