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
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8)->letters()->numbers());

        // Password reset links point at the SPA, never the API host.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => rtrim((string) config('app.frontend_url'), '/')
            .'/reset-password?token='.urlencode($token).'&email='.urlencode($user->email));

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));

        // Brute-force protection: per email+IP and per IP.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));
    }
}
