<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Deactivated users are rejected immediately and their current token revoked. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => __('messages.errors.account_inactive'),
                'code' => 'account_inactive',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
