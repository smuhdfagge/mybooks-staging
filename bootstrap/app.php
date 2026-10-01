<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminRole;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RedirectIfAdminAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyTenantOwnership;
use App\Services\ErrorAlerter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    // Listeners are mapped explicitly in AppServiceProvider::boot().
    // Auto-discovery is switched off so each listener is registered once
    // (with both on, every journal listener ran twice — finding N1).
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // A session holding an old password hash is signed out, so changing
        // or resetting a password ends every other sign-in (S5).
        $middleware->web(append: [AuthenticateSession::class]);
        // API errors are always JSON (I6).
        $middleware->api(prepend: [ForceJsonResponse::class]);

        $middleware->alias([
            'permission' => CheckPermission::class,
            'role' => CheckRole::class,
            'admin.auth' => AdminAuthenticate::class,
            'admin.guest' => RedirectIfAdminAuthenticated::class,
            'admin.role' => AdminRole::class,
            'admin.two-factor' => EnsureAdminTwoFactor::class,
            'subscription' => CheckSubscription::class,
            'plan' => CheckPlanAccess::class,
            'two-factor' => EnsureTwoFactorVerified::class,
            'tenant' => VerifyTenantOwnership::class,
            'active' => EnsureAccountActive::class,
            'feature' => EnsureFeatureEnabled::class,
            'idempotent' => EnsureIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Email server errors to the support address (finding O2). Only
        // reportable errors reach this (not 404s, validation, auth, etc.),
        // and the normal logging still happens.
        $exceptions->reportable(function (Throwable $e) {
            app(ErrorAlerter::class)->report($e);
        });

        // Everything under /api answers in the documented JSON error shape,
        // even without an Accept header or a matching route (I6).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && ! $e instanceof HttpResponseException) {
                return ApiExceptionRenderer::render($e, $request);
            }

            return null;
        });
    })->create();
