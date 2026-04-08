<?php

use App\Services\TelegramAlertService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant.scope'  => \App\Http\Middleware\EnsureTenantScope::class,
            'branch.access' => \App\Http\Middleware\EnsureBranchAccess::class,
            'role'           => \App\Http\Middleware\EnsureRole::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            if (app()->isProduction()
                && !$e instanceof \Illuminate\Auth\AuthenticationException
                && !$e instanceof \Illuminate\Validation\ValidationException
                && !$e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                && !$e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
                && !$e instanceof \Illuminate\Session\TokenMismatchException
            ) {
                try {
                    app(TelegramAlertService::class)->sendError($e);
                } catch (\Throwable) {
                    // Never let alerting break the app
                }
            }
        });
    })->create();
