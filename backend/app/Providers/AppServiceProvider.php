<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Stable, class-name-independent polymorphic keys (ledger sources, audit logs).
        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'company_document' => CompanyDocument::class,
            'product' => Product::class,
            'order' => Order::class,
            'shipment' => Shipment::class,
            'invoice' => Invoice::class,
            'payment' => Payment::class,
            'credit_note' => CreditNote::class,
            'ledger_entry' => LedgerEntry::class,
            'rfq' => Rfq::class,
        ]);

        // Fail fast in development: lazy loading (N+1), silently discarded attributes, missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Guard against destructive artisan commands (migrate:fresh, db:wipe) in production.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
