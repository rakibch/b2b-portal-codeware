<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\Localized;

class InvoiceOverdueNotification extends BaseNotification
{
    public function __construct(public readonly Invoice $invoice)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'invoice_overdue';
    }

    protected function replacements(): array
    {
        return ['number' => $this->invoice->invoice_number, 'amount' => Localized::currency($this->invoice->balanceDue()), 'due_date' => Localized::date($this->invoice->due_date)];
    }

    protected function path(): string
    {
        return '/invoices/'.$this->invoice->invoice_number;
    }
}
