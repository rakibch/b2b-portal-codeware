<?php

namespace App\Notifications;

use App\Models\Rfq;

class RfqQuotedNotification extends BaseNotification
{
    public function __construct(public readonly Rfq $rfq)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'rfq_quoted';
    }

    protected function replacements(): array
    {
        return ['reference' => $this->rfq->reference, 'valid_until' => (string) $this->rfq->valid_until?->toDateString()];
    }

    protected function path(): string
    {
        return '/rfqs/'.$this->rfq->reference;
    }
}
