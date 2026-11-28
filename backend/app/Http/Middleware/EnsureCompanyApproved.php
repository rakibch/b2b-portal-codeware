<?php

namespace App\Http\Middleware;

use App\Enums\KycStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** KYC approval gate: unapproved buyers cannot see wholesale prices, quote, cart or checkout. */
class EnsureCompanyApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('company');

        if ($user === null || ! $user->canTransact()) {
            $status = $user?->company?->kyc_status ?? KycStatus::Pending;

            return response()->json([
                'message' => __('messages.errors.kyc_'.$status->value),
                'code' => 'kyc_not_approved',
                'context' => ['kyc_status' => $status->value],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
