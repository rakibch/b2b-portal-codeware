<?php

use App\Http\Middleware\EnsureCompanyApproved;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserType;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorized;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->api(prepend: [SetLocale::class]);
        $middleware->throttleApi('api');

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'user.type' => EnsureUserType::class,
            'company.approved' => EnsureCompanyApproved::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Pure token API: never redirect unauthenticated requests to a login page.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $json = fn (string $code, int $status) => response()->json([
            'message' => __('messages.errors.'.$code),
            'code' => $code,
        ], $status);

        $exceptions->render(fn (AuthenticationException $e, Request $r) => $r->is('api/*') ? $json('unauthenticated', 401) : null);
        $exceptions->render(fn (AuthorizationException $e, Request $r) => $r->is('api/*') ? $json('forbidden', 403) : null);
        $exceptions->render(fn (AccessDeniedHttpException $e, Request $r) => $r->is('api/*') ? $json('forbidden', 403) : null);
        $exceptions->render(fn (SpatieUnauthorized $e, Request $r) => $r->is('api/*') ? $json('forbidden', 403) : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $r) => $r->is('api/*') ? $json('too_many_requests', 429)->withHeaders($e->getHeaders()) : null);
        // Never leak model class names / IDs on missing records.
        $exceptions->render(function (NotFoundHttpException $e, Request $r) use ($json) {
            return $r->is('api/*') ? $json('not_found', 404) : null;
        });
        $exceptions->render(fn (ModelNotFoundException $e, Request $r) => $r->is('api/*') ? $json('not_found', 404) : null);
    })->create();
