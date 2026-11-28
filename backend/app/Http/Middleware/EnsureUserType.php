<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('user.type:staff') or 'user.type:buyer'. Hard separation of portals. */
class EnsureUserType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $expected = UserType::from($type);

        if ($request->user()?->user_type !== $expected) {
            return response()->json([
                'message' => __('messages.errors.forbidden'),
                'code' => 'forbidden',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
