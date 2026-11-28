<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Localized;

class PaymentSubmittedNotification extends BaseNotification
{
    public function __construct(public readonly Payment $payment)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'payment_submitted';
    }

    protected function replacements(): array
    {
        return ['reference' => $this->payment->reference, 'company' => $this->payment->company->name, 'amount' => Localized::currency($this->payment->amount)];
    }

    protected function path(): string
    {
        return '/admin/payments/'.$this->payment->reference;
    }
}
