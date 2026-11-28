<?php

namespace App\Notifications;

use App\Models\Rfq;

class RfqDeclinedNotification extends BaseNotification
{
    public function __construct(public readonly Rfq $rfq)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'rfq_declined';
    }

    protected function replacements(): array
    {
        return ['reference' => $this->rfq->reference, 'reason' => (string) $this->rfq->quote_notes];
    }

    protected function path(): string
    {
        return '/rfqs/'.$this->rfq->reference;
    }
}
