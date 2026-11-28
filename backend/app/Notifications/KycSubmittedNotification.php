<?php

namespace App\Notifications;

use App\Models\Company;

class KycSubmittedNotification extends BaseNotification
{
    public function __construct(public readonly Company $company)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'kyc_submitted';
    }

    protected function replacements(): array
    {
        return ['company' => $this->company->name];
    }

    protected function path(): string
    {
        return '/admin/kyc/'.$this->company->public_id;
    }
}
