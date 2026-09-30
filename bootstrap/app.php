<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'admin.auth' => \App\Http\Middleware\AdminAuthenticate::class,
            'admin.guest' => \App\Http\Middleware\RedirectIfAdminAuthenticated::class,
            'admin.role' => \App\Http\Middleware\AdminRole::class,
            'admin.two-factor' => \App\Http\Middleware\EnsureAdminTwoFactor::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'plan' => \App\Http\Middleware\CheckPlanAccess::class,
            'two-factor' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'tenant' => \App\Http\Middleware\VerifyTenantOwnership::class,
            'active' => \App\Http\Middleware\EnsureAccountActive::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Email server errors to the support address (finding O2). Only
        // reportable errors reach this (not 404s, validation, auth, etc.),
        // and the normal logging still happens.
        $exceptions->reportable(function (Throwable $e) {
            app(\App\Services\ErrorAlerter::class)->report($e);
        });
    })->create();
