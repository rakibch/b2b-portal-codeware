<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Localized;

class PaymentRejectedNotification extends BaseNotification
{
    public function __construct(public readonly Payment $payment)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'payment_rejected';
    }

    protected function replacements(): array
    {
        return ['reference' => $this->payment->reference, 'amount' => Localized::currency($this->payment->amount), 'reason' => (string) $this->payment->rejection_reason];
    }

    protected function path(): string
    {
        return '/payments';
    }
}
