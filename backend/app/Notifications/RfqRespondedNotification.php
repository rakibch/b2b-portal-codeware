<?php

namespace App\Notifications;

use App\Models\Rfq;

class RfqRespondedNotification extends BaseNotification
{
    public function __construct(public readonly Rfq $rfq)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'rfq_responded';
    }

    protected function replacements(): array
    {
        return ['reference' => $this->rfq->reference, 'company' => $this->rfq->company->name, 'status' => $this->rfq->status->label()];
    }

    protected function path(): string
    {
        return '/admin/rfqs/'.$this->rfq->reference;
    }
}
