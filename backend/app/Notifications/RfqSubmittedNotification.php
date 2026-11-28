<?php

namespace App\Notifications;

use App\Models\Rfq;

class RfqSubmittedNotification extends BaseNotification
{
    public function __construct(public readonly Rfq $rfq)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'rfq_submitted';
    }

    protected function replacements(): array
    {
        return ['reference' => $this->rfq->reference, 'company' => $this->rfq->company->name];
    }

    protected function path(): string
    {
        return '/admin/rfqs/'.$this->rfq->reference;
    }
}
