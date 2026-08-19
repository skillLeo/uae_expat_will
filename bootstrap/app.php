<?php

use App\Http\Middleware\EnsureClientPortalEnabled;
use App\Http\Middleware\EnsureTwoFactorChallengePassed;
use App\Http\Middleware\EnsureTwoFactorIsConfirmed;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackDeviceSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')
                ->group(base_path('routes/client.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs before everything else on a web request, including Authenticate.
        $middleware->web(prepend: [
            EnsureClientPortalEnabled::class,
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackDeviceSession::class,
        ]);

        $middleware->alias([
            'admin.2fa' => EnsureTwoFactorIsConfirmed::class,
            'admin.2fa.passed' => EnsureTwoFactorChallengePassed::class,
            'client.enabled' => EnsureClientPortalEnabled::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Where an unauthenticated request is sent depends on WHICH guard it
        // failed. An admin must land on the admin sign-in, never on the public
        // site, or the redirect itself leaks which area they were reaching for.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin*')
            ? route('admin.login')
            : url('/'));

        // The webhook route verifies its own signature and must not be blocked by
        // CSRF — the gateway has no session and no token to send.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Error pages are real pages, not Laravel's default stack trace or a
        // bare status code. Each states what happened and what it means for the
        // reader's matter. Admin and client areas keep the plain response —
        // a marketing shell around an internal 403 is noise.
        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson()
                || app()->environment('local', 'testing')
                || ! in_array($status, [403, 404, 419, 500, 503], true)) {
                return $response;
            }

            // Share the props the layout needs. This response is built
            // outside the Inertia middleware, so nothing is shared onto it
            // automatically — without this the footer and header render empty.
            Inertia::share((new HandleInertiaRequests)->share($request));

            return Inertia::render('Error', [
                'status' => $status,
                'reference' => $status === 500
                    ? 'ERR-'.now()->format('Y-m-d').'-'.substr(md5((string) $exception->getMessage()), 0, 4)
                    : null,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
