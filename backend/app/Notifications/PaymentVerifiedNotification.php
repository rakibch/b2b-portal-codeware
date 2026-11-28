<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Localized;

class PaymentVerifiedNotification extends BaseNotification
{
    public function __construct(public readonly Payment $payment)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'payment_verified';
    }

    protected function replacements(): array
    {
        return ['reference' => $this->payment->reference, 'amount' => Localized::currency($this->payment->amount)];
    }

    protected function path(): string
    {
        return '/ledger';
    }
}
