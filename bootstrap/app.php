<?php

use App\Billing\Exceptions\GatewayValidationException;
use App\Exceptions\Billing\CustomerNotRegisteredInGatewayException;
use App\Exceptions\Billing\PriceMissingGatewayMappingException;
use App\Exceptions\Billing\PriceNotFoundException;
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
use Illuminate\Http\Request;
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

        // PR-F: Stripe webhook auth is the signature header, not CSRF.
        $middleware->validateCsrfTokens(except: [
            'billing/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ── Billing exception mappings (PR-E, ADR-016) ──
        $exceptions->render(function (PriceNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No price available for the requested plan/currency/interval.',
                    'error' => 'price_not_found',
                ], 422);
            }
        });
        $exceptions->render(function (PriceMissingGatewayMappingException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Price is not configured for the target gateway. Contact support.',
                    'error' => 'price_gateway_mapping_missing',
                ], 422);
            }
        });
        $exceptions->render(function (CustomerNotRegisteredInGatewayException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Customer is not registered in the payment gateway yet. Create the customer first.',
                    'error' => 'customer_not_registered',
                ], 409);
            }
        });
        $exceptions->render(function (GatewayValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Payment gateway rejected the request.',
                    'error' => 'gateway_validation_failed',
                    'detail' => $e->getMessage(),
                ], 422);
            }
        });

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
