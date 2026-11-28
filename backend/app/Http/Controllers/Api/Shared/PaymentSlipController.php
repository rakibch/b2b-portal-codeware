<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentSlipController extends Controller
{
    public function __invoke(Payment $payment): StreamedResponse
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->hasSlip() && Storage::disk($payment->slip_disk)->exists($payment->slip_path), 404);

        return Storage::disk($payment->slip_disk)->download($payment->slip_path, $payment->reference.'-slip.'.pathinfo($payment->slip_path, PATHINFO_EXTENSION), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
