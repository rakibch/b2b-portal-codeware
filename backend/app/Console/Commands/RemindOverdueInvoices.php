<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Attribute\AsCommand;

/** Reminds buyer admins on day 1, 7, 14 and 30 after the due date (no daily spam). */
#[AsCommand(name: 'invoices:remind-overdue', description: 'Send overdue invoice reminders to buyer administrators')]
class RemindOverdueInvoices extends Command
{
    private const REMINDER_DAYS = [1, 7, 14, 30];

    public function handle(): int
    {
        $dates = array_map(fn (int $d) => today()->subDays($d)->toDateString(), self::REMINDER_DAYS);
        $sent = 0;

        Invoice::query()
            ->outstanding()
            ->where(function ($q) use ($dates): void {
                foreach ($dates as $date) {
                    $q->orWhereDate('due_date', $date);
                }
            })
            ->with('company')
            ->chunkById(200, function ($invoices) use (&$sent): void {
                foreach ($invoices as $invoice) {
                    $admins = User::query()->where('company_id', $invoice->company_id)->active()->role(Role::BuyerAdmin->value)->get();
                    Notification::send($admins, new InvoiceOverdueNotification($invoice));
                    $sent++;
                }
            });

        $this->info("Sent reminders for {$sent} overdue invoice(s).");

        return self::SUCCESS;
    }
}
