<?php

declare(strict_types=1);

namespace App\Providers;

use App\Billing\Contracts\BillingGateway;
use App\Billing\Stripe\StripeBillingGateway;
use App\Billing\Stripe\StripeClientFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the billing module's service bindings.
 *
 * Currently a single binding: BillingGateway → StripeBillingGateway.
 * The container resolves a fresh StripeClient per request from the
 * factory, so test bindings can swap in mocks without globally
 * mutating Stripe SDK state.
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
    }

    public function boot(): void
    {
        // Nothing yet. PR-H will publish the outbox listener here.
    }
}
