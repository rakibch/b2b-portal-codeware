<?php

namespace App\Services\Payment;

final readonly class GatewayResult
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public function __construct(
        public string $status,
        public string $paymentReference,
        public string $transactionId,
        public string $amount,
        public string $currency,
        public array $raw = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === self::SUCCESS;
    }
}
