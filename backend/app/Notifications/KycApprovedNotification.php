<?php

namespace App\Notifications;

use App\Models\Company;

class KycApprovedNotification extends BaseNotification
{
    public function __construct(public readonly Company $company)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'kyc_approved';
    }

    protected function replacements(): array
    {
        return ['company' => $this->company->name];
    }

    protected function path(): string
    {
        return '/dashboard';
    }
}
