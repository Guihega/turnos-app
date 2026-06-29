<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Actions\Billing\CreateCustomerAction;
use App\Actions\Billing\CreatePilotCustomerAction;
use App\Actions\Billing\CreatePilotSubscriptionAction;
use App\Actions\Billing\CreateSetupIntentAction;
use App\Actions\Billing\CreateSubscriptionAction;
use App\Actions\Billing\MaterializeEntitlementsAction;
use App\Actions\Billing\OnboardPilotAction;
use App\Actions\Billing\TransitionSubscriptionAction;
use App\Actions\Billing\UpdatePaymentMethodAction;
use App\Billing\Contracts\BillingGateway;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\Outbox\Handlers\PastDueEnteredHandler;
use App\Billing\Outbox\Handlers\SubscriptionSuspendedHandler;
use App\Billing\Stripe\Mappers\StripeSubscriptionStatusMapper;
use App\Billing\Stripe\StripeBillingGateway;
use App\Billing\Stripe\StripeClientFactory;
use App\Billing\Webhooks\Handlers\InvoicePaymentFailedHandler;
use App\Billing\Webhooks\Handlers\SubscriptionDeletedHandler;
use App\Billing\Webhooks\Handlers\SubscriptionUpdatedHandler;
use App\Billing\Webhooks\Handlers\TrialWillEndHandler;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\OutboxEventDispatcher;
use App\Services\Billing\OutboxEventWriter;
use App\Services\Billing\PriceResolver;
use App\Services\Billing\WebhookEventDispatcher;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Architecture test: every concrete service class in the Billing
 * module MUST be resolvable from the Laravel container without
 * manual `instance()` overrides.
 *
 * Rationale: PR-H shipped OutboxEventDispatcher without a provider
 * binding. Tests passed because every test injected the dispatcher
 * via `$this->app->instance(...)`. In production, the scheduled
 * publisher job would have failed at the first tick with
 * BindingResolutionException. The bug was caught only when PR-I
 * tried to consume the dispatcher from the wired path.
 *
 * This test is the structural guard: any class added to the billing
 * namespaces that requires container resolution will be exercised
 * here. If the test fails, either:
 *   (a) the class needs a binding in BillingServiceProvider, OR
 *   (b) the class should not be auto-resolvable (e.g. it's a DTO
 *       miscategorized) — in which case, exclude it explicitly
 *       below with a comment explaining why.
 *
 * See PR-K and the PR-H retrospective for context.
 */
final class BillingContainerBindingsTest extends TestCase
{
    /**
     * Stub the Stripe secret key so StripeClientFactory's eager validation
     * passes during container resolution. This audit only verifies that the
     * container CAN resolve every billing service, not that Stripe config
     * is valid in the runtime environment — that's the responsibility of
     * StripeConnectivitySmokeTest (env-gated, runs against Stripe test mode).
     *
     * The stub value is never used for an actual API call: this test only
     * resolves the binding graph. PR-D / ADR-015 chose fail-fast validation
     * in StripeClientFactory; this stub is the documented escape hatch for
     * environments without Stripe credentials (CI default).
     */
    protected function setUp(): void
    {
        parent::setUp();

        Config::set(
            'billing.gateways.stripe.secret_key',
            'sk_test_stub_for_container_audit_only'
        );
    }

    /**
     * Concrete service classes that MUST be container-resolvable.
     *
     * @return iterable<string, array{0: class-string}>
     */
    public static function billingServices(): iterable
    {
        // Actions
        yield 'CreateCustomerAction' => [CreateCustomerAction::class];
        yield 'CreatePilotCustomerAction' => [CreatePilotCustomerAction::class];
        yield 'CreatePilotSubscriptionAction' => [CreatePilotSubscriptionAction::class];
        yield 'CreateSetupIntentAction' => [CreateSetupIntentAction::class];
        yield 'CreateSubscriptionAction' => [CreateSubscriptionAction::class];
        yield 'MaterializeEntitlementsAction' => [MaterializeEntitlementsAction::class];
        yield 'OnboardPilotAction' => [OnboardPilotAction::class];
        yield 'TransitionSubscriptionAction' => [TransitionSubscriptionAction::class];
        yield 'UpdatePaymentMethodAction' => [UpdatePaymentMethodAction::class];

        // Outbox handlers (PR-I)
        yield 'PastDueEnteredHandler' => [PastDueEnteredHandler::class];
        yield 'SubscriptionSuspendedHandler' => [SubscriptionSuspendedHandler::class];

        // Stripe adapter
        yield 'StripeBillingGateway' => [StripeBillingGateway::class];
        yield 'StripeClientFactory' => [StripeClientFactory::class];

        // Webhook handlers (PR-G)
        yield 'InvoicePaymentFailedHandler' => [InvoicePaymentFailedHandler::class];
        yield 'SubscriptionDeletedHandler' => [SubscriptionDeletedHandler::class];
        yield 'SubscriptionUpdatedHandler' => [SubscriptionUpdatedHandler::class];
        yield 'TrialWillEndHandler' => [TrialWillEndHandler::class];

        // Services
        yield 'EntitlementService' => [EntitlementService::class];
        yield 'OutboxEventDispatcher' => [OutboxEventDispatcher::class];
        yield 'OutboxEventWriter' => [OutboxEventWriter::class];
        yield 'PriceResolver' => [PriceResolver::class];
        yield 'WebhookEventDispatcher' => [WebhookEventDispatcher::class];
    }

    /**
     * Interface → implementation bindings that the container must satisfy.
     *
     * @return iterable<string, array{0: class-string, 1: class-string}>
     */
    public static function billingContractBindings(): iterable
    {
        yield 'BillingGateway resolves to StripeBillingGateway' => [
            BillingGateway::class,
            StripeBillingGateway::class,
        ];
        yield 'BillingGatewayWriter resolves to StripeBillingGateway' => [
            BillingGatewayWriter::class,
            StripeBillingGateway::class,
        ];
    }

    /**
     * Concrete classes in the audited namespaces that are intentionally
     * NOT services and therefore not subject to the container resolution
     * audit. Each entry MUST document why.
     *
     * @return array<class-string, string> fqcn => rationale
     */
    public static function excludedFromBindingAudit(): array
    {
        return [
            // Stateless mapper exposing only static methods. Never instantiated,
            // never resolved by the container. Lives in the Billing namespace
            // by topical proximity, not by service character.
            StripeSubscriptionStatusMapper::class => 'Stateless static-only mapper; not a container-resolvable service.',
        ];
    }

    #[Test]
    #[DataProvider('billingServices')]
    public function concrete_service_is_resolvable_by_container(string $class): void
    {
        $this->assertTrue(class_exists($class), "Class {$class} does not exist on disk.");

        try {
            $instance = $this->app->make($class);
        } catch (BindingResolutionException $e) {
            $this->fail(
                "Container cannot resolve {$class}: {$e->getMessage()}\n"
                .'Either bind it in BillingServiceProvider::register(), '
                .'or exclude it from this test with a documented reason.'
            );
        } catch (Throwable $e) {
            $this->fail(
                "Unexpected error resolving {$class}: ".$e::class.': '.$e->getMessage()
            );
        }

        $this->assertInstanceOf($class, $instance);
    }

    #[Test]
    #[DataProvider('billingContractBindings')]
    public function contract_resolves_to_expected_implementation(string $contract, string $expectedImplementation): void
    {
        $this->assertTrue(interface_exists($contract), "Interface {$contract} does not exist.");
        $this->assertTrue(class_exists($expectedImplementation), "Implementation {$expectedImplementation} does not exist.");

        try {
            $instance = $this->app->make($contract);
        } catch (BindingResolutionException $e) {
            $this->fail(
                "Container cannot resolve {$contract}: {$e->getMessage()}\n"
                .'Bind it explicitly in BillingServiceProvider::register().'
            );
        }

        $this->assertInstanceOf(
            $expectedImplementation,
            $instance,
            "Expected {$contract} to resolve to {$expectedImplementation}, got ".$instance::class
        );
    }

    #[Test]
    public function no_billing_service_class_was_added_without_test_coverage(): void
    {
        // Discover every concrete class under the audited namespaces.
        $namespaces = [
            'app/Actions/Billing' => 'App\\Actions\\Billing\\',
            'app/Billing/Outbox/Handlers' => 'App\\Billing\\Outbox\\Handlers\\',
            'app/Billing/Stripe' => 'App\\Billing\\Stripe\\',
            'app/Billing/Webhooks/Handlers' => 'App\\Billing\\Webhooks\\Handlers\\',
            'app/Services/Billing' => 'App\\Services\\Billing\\',
        ];

        $discovered = [];
        foreach ($namespaces as $path => $namespace) {
            $abs = base_path($path);
            if (! is_dir($abs)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs)) as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $relative = str_replace([$abs.'/', '.php', '/'], ['', '', '\\'], $file->getPathname());
                $fqcn = $namespace.$relative;

                if (! class_exists($fqcn)) {
                    continue;
                }
                $reflection = new \ReflectionClass($fqcn);
                if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait() || $reflection->isEnum()) {
                    continue;
                }
                $discovered[] = $fqcn;
            }
        }

        // Compare against the explicit list in the data provider,
        // subtracting documented exclusions (non-service classes living
        // in audited namespaces for topical reasons).
        $declared = array_map(static fn (array $row): string => $row[0], iterator_to_array(self::billingServices()));
        $excluded = array_keys(self::excludedFromBindingAudit());

        $missing = array_diff($discovered, $declared, $excluded);

        $this->assertEmpty(
            $missing,
            "The following billing service classes are NOT covered by the bindings audit:\n"
            .implode("\n", $missing)
            ."\n\nAdd each to billingServices() or document why it should be excluded."
        );
    }
}
