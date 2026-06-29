<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Database;

use App\Models\Billing\Customer;
use App\Models\Billing\IdempotencyKey;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Schema-level tests for billing_idempotency_keys.
 *
 * These tests run the real migrations against the test DB and assert
 * the table behaves per ADR-016: ULID PK, optional FK to customer,
 * unique (gateway, idempotency_key), and Carbon-cast timestamps.
 */
final class IdempotencyKeysSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('billing_idempotency_keys'));

        $expected = [
            'id',
            'customer_id',
            'operation',
            'gateway',
            'idempotency_key',
            'request_hash',
            'response_snapshot',
            'created_at',
            'expires_at',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('billing_idempotency_keys', $col),
                "Column {$col} missing from billing_idempotency_keys"
            );
        }
    }

    #[Test]
    public function can_insert_an_idempotency_key_without_customer(): void
    {
        $key = IdempotencyKey::create([
            'customer_id' => null,
            'operation' => 'create_customer',
            'gateway' => 'stripe',
            'idempotency_key' => '01HX_TEST_KEY_001',
            'request_hash' => str_repeat('a', 64),
            'response_snapshot' => ['gatewayId' => 'cus_TEST'],
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertNotNull($key->id);
        $this->assertNull($key->customer_id);
        $this->assertSame('stripe', $key->gateway);
        $this->assertSame(['gatewayId' => 'cus_TEST'], $key->response_snapshot);
    }

    #[Test]
    public function can_insert_an_idempotency_key_attached_to_a_customer(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'country' => 'MX',
            'default_currency' => 'MXN',
            'billing_email' => 'owner@acme.test',
        ]);

        $key = IdempotencyKey::create([
            'customer_id' => $customer->id,
            'operation' => 'create_subscription',
            'gateway' => 'stripe',
            'idempotency_key' => '01HX_TEST_KEY_002',
            'request_hash' => str_repeat('b', 64),
            'response_snapshot' => null,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertSame($customer->id, $key->customer_id);
        $this->assertInstanceOf(Customer::class, $key->customer);
        $this->assertSame($customer->id, $key->customer->id);
    }

    #[Test]
    public function unique_constraint_on_gateway_and_key_is_enforced(): void
    {
        IdempotencyKey::create([
            'customer_id' => null,
            'operation' => 'create_customer',
            'gateway' => 'stripe',
            'idempotency_key' => '01HX_DUPLICATE_KEY',
            'request_hash' => str_repeat('a', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->expectException(QueryException::class);

        IdempotencyKey::create([
            'customer_id' => null,
            'operation' => 'create_subscription',
            'gateway' => 'stripe',
            'idempotency_key' => '01HX_DUPLICATE_KEY', // same key, same gateway
            'request_hash' => str_repeat('c', 64),
            'expires_at' => now()->addDays(7),
        ]);
    }

    #[Test]
    public function same_key_string_is_allowed_across_different_gateways(): void
    {
        $sharedString = '01HX_SHARED_ACROSS_GATEWAYS';

        IdempotencyKey::create([
            'customer_id' => null,
            'operation' => 'create_customer',
            'gateway' => 'stripe',
            'idempotency_key' => $sharedString,
            'request_hash' => str_repeat('a', 64),
            'expires_at' => now()->addDays(7),
        ]);

        // Same string, different gateway: should NOT trigger UNIQUE violation.
        $second = IdempotencyKey::create([
            'customer_id' => null,
            'operation' => 'create_customer',
            'gateway' => 'mercadopago',
            'idempotency_key' => $sharedString,
            'request_hash' => str_repeat('b', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertNotNull($second->id);
        $this->assertSame(2, IdempotencyKey::where('idempotency_key', $sharedString)->count());
    }

    #[Test]
    public function not_expired_scope_excludes_past_keys(): void
    {
        IdempotencyKey::create([
            'customer_id' => null,
            'operation' => 'create_customer',
            'gateway' => 'stripe',
            'idempotency_key' => '01HX_EXPIRED',
            'request_hash' => str_repeat('a', 64),
            'expires_at' => now()->subDay(),
        ]);
        IdempotencyKey::create([
            'customer_id' => null,
            'operation' => 'create_customer',
            'gateway' => 'stripe',
            'idempotency_key' => '01HX_FRESH',
            'request_hash' => str_repeat('b', 64),
            'expires_at' => now()->addDay(),
        ]);

        $this->assertSame(1, IdempotencyKey::notExpired()->count());
        $this->assertSame(1, IdempotencyKey::expired()->count());
    }

    #[Test]
    public function customer_deletion_nulls_the_fk(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'country' => 'MX',
            'default_currency' => 'MXN',
            'billing_email' => 'owner@acme.test',
        ]);

        $key = IdempotencyKey::create([
            'customer_id' => $customer->id,
            'operation' => 'create_subscription',
            'gateway' => 'stripe',
            'idempotency_key' => '01HX_FK_TEST',
            'request_hash' => str_repeat('a', 64),
            'expires_at' => now()->addDays(7),
        ]);

        // Hard delete (forceDelete bypasses SoftDeletes on Customer).
        $customer->forceDelete();

        $key->refresh();
        $this->assertNull($key->customer_id);
    }
}
