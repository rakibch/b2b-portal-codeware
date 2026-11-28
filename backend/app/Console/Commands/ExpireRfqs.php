<?php

namespace App\Console\Commands;

use App\Services\Rfq\RfqService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'rfqs:expire', description: 'Mark quoted RFQs past their validity date as expired')]
class ExpireRfqs extends Command
{
    public function handle(RfqService $rfqs): int
    {
        $count = $rfqs->expireDue();
        $this->info("Expired {$count} RFQ(s).");

        return self::SUCCESS;
    }
}
