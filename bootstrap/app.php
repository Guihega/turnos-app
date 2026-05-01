<?php

use App\Http\Middleware\EnsureBranchAccess;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTenantScope;
use App\Http\Middleware\HandleInertiaRequests;
use App\Services\TelegramAlertService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant.scope' => EnsureTenantScope::class,
            'branch.access' => EnsureBranchAccess::class,
            'role' => EnsureRole::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            if (app()->isProduction()
                && ! $e instanceof AuthenticationException
                && ! $e instanceof ValidationException
                && ! $e instanceof NotFoundHttpException
                && ! $e instanceof MethodNotAllowedHttpException
                && ! $e instanceof TokenMismatchException
            ) {
                try {
                    app(TelegramAlertService::class)->sendError($e);
                } catch (Throwable) {
                    // Never let alerting break the app
                }
            }
        });
    })->create();
