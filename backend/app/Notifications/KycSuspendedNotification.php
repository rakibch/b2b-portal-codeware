<?php

namespace App\Notifications;

use App\Models\Company;

class KycSuspendedNotification extends BaseNotification
{
    public function __construct(public readonly Company $company, public readonly string $reason)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'kyc_suspended';
    }

    protected function replacements(): array
    {
        return ['company' => $this->company->name, 'reason' => $this->reason];
    }

    protected function path(): string
    {
        return '/kyc/pending';
    }
}
