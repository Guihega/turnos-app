<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Stripe;

use App\Billing\Stripe\StripeClientFactory;
use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Stripe\StripeClient;
use Tests\TestCase;

final class SyncStripeCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function mockStripeClient(): MockInterface
    {
        $client = Mockery::mock(StripeClient::class);

        $products = Mockery::mock();
        $products->shouldReceive('search')->andReturn((object) ['data' => []])->byDefault();
        $products->shouldReceive('create')->andReturn((object) ['id' => 'prod_TEST123'])->byDefault();

        $prices = Mockery::mock();
        $counter = 0;
        $prices->shouldReceive('create')->andReturnUsing(function () use (&$counter) {
            $counter++;

            return (object) ['id' => 'price_TEST'.$counter];
        })->byDefault();

        $client->products = $products;
        $client->prices = $prices;

        return $client;
    }

    private function bindFactory(StripeClient|MockInterface $client): void
    {
        $this->app->instance(StripeClientFactory::class, new FakeStripeClientFactory($client));

        config()->set('billing.gateways.stripe.mode', 'test');
        config()->set('billing.gateways.stripe.secret_key', 'sk_test_fake');
        config()->set('billing.gateways.stripe.api_version', '2024-11-20.acacia');
    }

    private function makePlanWithPrice(string $code, int $amountCents): Price
    {
        $plan = Plan::create([
            'code' => $code,
            'name' => ucfirst($code),
            'description' => 'Plan '.$code,
            'is_public' => true,
            'is_active' => true,
            'sort_order' => 1,
            'metadata' => null,
        ]);

        return Price::create([
            'plan_id' => $plan->id,
            'currency' => 'MXN',
            'country' => null,
            'interval' => BillingInterval::Month,
            'interval_count' => 1,
            'amount_cents' => $amountCents,
            'tax_behavior' => 'inclusive',
            'gateway_refs' => null,
            'is_active' => true,
        ]);
    }

    public function test_crea_price_en_stripe_y_persiste_gateway_ref(): void
    {
        $this->bindFactory($this->mockStripeClient());
        $price = $this->makePlanWithPrice('professional', 139900);

        $this->artisan('billing:sync-stripe-catalog')->assertExitCode(0);

        $price->refresh();
        $this->assertNotNull($price->gateway_refs);
        $this->assertArrayHasKey('stripe', $price->gateway_refs);
        $this->assertStringStartsWith('price_TEST', $price->gateway_refs['stripe']);
    }

    public function test_es_idempotente_salta_precios_ya_vinculados(): void
    {
        $client = $this->mockStripeClient();
        $client->prices->shouldReceive('create')->never();
        $this->bindFactory($client);

        $price = $this->makePlanWithPrice('starter', 49900);
        $price->gateway_refs = ['stripe' => 'price_YA_EXISTE'];
        $price->save();

        $this->artisan('billing:sync-stripe-catalog')->assertExitCode(0);

        $price->refresh();
        $this->assertSame('price_YA_EXISTE', $price->gateway_refs['stripe']);
    }

    public function test_omite_precios_de_monto_cero(): void
    {
        $client = $this->mockStripeClient();
        $client->products->shouldReceive('create')->never();
        $client->prices->shouldReceive('create')->never();
        $this->bindFactory($client);

        $price = $this->makePlanWithPrice('pilot', 0);

        $this->artisan('billing:sync-stripe-catalog')->assertExitCode(0);

        $price->refresh();
        $this->assertNull($price->gateway_refs);
    }

    public function test_dry_run_no_escribe_ni_llama_a_stripe(): void
    {
        $client = $this->mockStripeClient();
        $client->products->shouldReceive('create')->never();
        $client->prices->shouldReceive('create')->never();
        $this->bindFactory($client);

        $price = $this->makePlanWithPrice('business', 349900);

        $this->artisan('billing:sync-stripe-catalog', ['--dry-run' => true])->assertExitCode(0);

        $price->refresh();
        $this->assertNull($price->gateway_refs);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

class FakeStripeClientFactory extends StripeClientFactory
{
    public function __construct(private StripeClient $client)
    {
    }

    public function make(): StripeClient
    {
        return $this->client;
    }
}
