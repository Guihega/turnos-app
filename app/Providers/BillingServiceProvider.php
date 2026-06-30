<?php

declare(strict_types=1);

namespace App\Providers;

use App\Billing\Contracts\BillingGateway;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\Stripe\StripeBillingGateway;
use App\Billing\Stripe\StripeClientFactory;
use App\Billing\Webhooks\Handlers\InvoicePaidHandler;
use App\Billing\Webhooks\Handlers\InvoicePaymentFailedHandler;
use App\Billing\Webhooks\Handlers\SubscriptionDeletedHandler;
use App\Billing\Webhooks\Handlers\SubscriptionUpdatedHandler;
use App\Billing\Webhooks\Handlers\TrialWillEndHandler;
use App\Services\Billing\OutboxEventDispatcher;
use App\Services\Billing\WebhookEventDispatcher;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the billing module's service bindings.
 *
 * Webhook handlers are hardcoded here because the mapping of external
 * Stripe events is part of the gateway contract and rarely changes
 * without code. Outbox handlers, by contrast, are read from
 * config/billing.php — they map internal domain events, evolve with
 * features, and benefit from being listed in a single discoverable
 * config file.
 */
final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StripeClientFactory::class, function ($app): StripeClientFactory {
            /** @var Repository $config */
            $config = $app->make(Repository::class);

            return new StripeClientFactory($config);
        });

        $this->app->bind(BillingGateway::class, function ($app): StripeBillingGateway {
            /** @var StripeClientFactory $factory */
            $factory = $app->make(StripeClientFactory::class);
            /** @var Repository $config */
            $config = $app->make(Repository::class);

            return new StripeBillingGateway(
                client: $factory->make(),
                config: $config,
            );
        });

        // PR-E: write side of the gateway. Same Stripe adapter
        // implements both interfaces.
        $this->app->bind(BillingGatewayWriter::class, function ($app): StripeBillingGateway {
            /** @var StripeBillingGateway $gateway */
            $gateway = $app->make(BillingGateway::class);

            return $gateway;
        });

        // PR-G: webhook event dispatcher with the production handler map.
        // Singleton — the map is immutable for the request lifecycle.
        $this->app->singleton(WebhookEventDispatcher::class, function ($app): WebhookEventDispatcher {
            /** @var Container $container */
            $container = $app;

            return new WebhookEventDispatcher(
                handlers: [
                    'customer.subscription.updated' => SubscriptionUpdatedHandler::class,
                    'customer.subscription.deleted' => SubscriptionDeletedHandler::class,
                    'customer.subscription.trial_will_end' => TrialWillEndHandler::class,
                    'invoice.paid' => InvoicePaidHandler::class,
                    'invoice.payment_failed' => InvoicePaymentFailedHandler::class,
                ],
                container: $container,
            );
        });

        // PR-H/I: outbox event dispatcher, handlers sourced from config.
        // Singleton — the handler map is immutable per request.
        $this->app->singleton(OutboxEventDispatcher::class, function ($app): OutboxEventDispatcher {
            /** @var Container $container */
            $container = $app;
            /** @var array<string, class-string|list<class-string>> $handlers */
            $handlers = (array) config('billing.outbox.handlers', []);

            return new OutboxEventDispatcher(
                handlers: $handlers,
                container: $container,
            );
        });
    }

    public function boot(): void
    {
        // Intentionally empty. Outbox persistence is invoked explicitly
        // from producers (Actions, Handlers) inside their DB transactions,
        // not via a wildcard event listener — see ADR-013 / SubscriptionDomainEvent.
    }
}
