<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/auth.php'));

            Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'admin', 'admin.nav'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'admin.nav' => \App\Http\Middleware\MarkAdminNavSeen::class,
            'inventory.unlocked' => \App\Http\Middleware\EnsureInventoryUnlocked::class,
        ]);

        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->prepend([
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            // Ties each session to the current password hash, so changing a
            // password really does end every other signed-in session.
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\SetStorefrontLocale::class,
            \App\Http\Middleware\UseSeeOtherRedirectsForFormPosts::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'payments/whish/callback/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
