<?php

namespace App\Notifications;

use App\Models\Company;

class KycRejectedNotification extends BaseNotification
{
    public function __construct(public readonly Company $company)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'kyc_rejected';
    }

    protected function replacements(): array
    {
        return ['company' => $this->company->name, 'reason' => (string) $this->company->kyc_rejection_reason];
    }

    protected function path(): string
    {
        return '/kyc/pending';
    }
}
