<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\GeneratesIdempotencyKeys;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\CreateCustomerInput;
use App\Enums\Billing\Gateway;
use App\Events\Billing\CustomerCreated;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Customer in both the gateway and the local DB.
 *
 * Per ADR-016, this is a saga:
 *
 *   Step 1. Build the gateway-agnostic payload from $tenant + $details.
 *   Step 2. Lookup or mint an idempotency key for this logical request.
 *   Step 3. If the key already has a cached response, reuse the existing
 *           local Customer (transparent retry).
 *   Step 4. Otherwise, call the gateway. Failures propagate; nothing is
 *           written to DB.
 *   Step 5. In a single DB::transaction, create Customer +
 *           CustomerGatewayRef, snapshot the response, and link the
 *           idempotency key to the new customer.
 *   Step 6. Dispatch CustomerCreated post-commit.
 *
 * Failure semantics:
 *
 *   - Gateway exception → re-thrown. No DB writes. Caller may retry,
 *     and the idempotency key (already minted as a fresh row in step 2)
 *     will be reused, so the gateway dedupes.
 *
 *   - DB write fails AFTER gateway returned ok → the gateway has an
 *     orphan customer, but the idempotency key snapshot is empty so a
 *     retry hits the gateway again with the same key, gets the same
 *     customer back (idempotent at gateway level), and completes the
 *     local writes. ADR-016 §5.
 *
 * @see docs/adr/ADR-016-billing-write-contract-and-create-flows.md
 */
final class CreateCustomerAction
{
    use GeneratesIdempotencyKeys;

    public function __construct(
        private readonly BillingGatewayWriter $writer,
    ) {}

    /**
     * @param  array{
     *     billing_email: string,
     *     billing_name?: string|null,
     *     country?: string,
     *     default_currency?: string,
     *     tax_id?: string|null,
     *     billing_address?: array<string, mixed>|null,
     * }  $details
     */
    public function execute(Tenant $tenant, array $details): Customer
    {
        $gateway = Gateway::Stripe;
        $country = $details['country'] ?? 'MX';
        $currency = $details['default_currency'] ?? 'MXN';

        // Step 1: Build the gateway payload. The hash is computed over
        // these stable fields; presence of unrelated $details keys
        // (e.g. internal flags) does not affect idempotency.
        $metadata = [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug ?? '',
        ];

        $hashablePayload = [
            'tenant_id' => $tenant->id,
            'email' => $details['billing_email'],
            'name' => $details['billing_name'] ?? null,
            'country' => $country,
            'tax_id' => $details['tax_id'] ?? null,
        ];

        // Step 2: Find or create the idempotency key.
        // customer_id is null at this point — there is no local
        // customer yet. We link it after the DB insert in step 5.
        $key = $this->findOrCreateIdempotencyKey(
            operation: 'create_customer',
            gateway: $gateway,
            payload: $hashablePayload,
            customerId: null,
        );

        // Step 3: If we already have a snapshot, the previous attempt
        // committed locally; just return the existing customer.
        if ($key->response_snapshot !== null && $key->customer_id !== null) {
            /** @var Customer $existing */
            $existing = Customer::findOrFail($key->customer_id);

            return $existing;
        }

        // Step 4: Call the gateway.
        $input = new CreateCustomerInput(
            email: $details['billing_email'],
            name: $details['billing_name'] ?? null,
            country: $country,
            taxId: $details['tax_id'] ?? null,
            metadata: $metadata,
        );
        $gatewayCustomer = $this->writer->createCustomer($input, $key->idempotency_key);

        // Step 5: Persist locally inside one transaction.
        $customer = DB::transaction(function () use (
            $tenant,
            $details,
            $country,
            $currency,
            $gateway,
            $gatewayCustomer,
            $key,
        ): Customer {
            /** @var Customer $customer */
            $customer = Customer::create([
                'tenant_id' => $tenant->id,
                'country' => $country,
                'default_currency' => $currency,
                'billing_email' => $details['billing_email'],
                'billing_name' => $details['billing_name'] ?? null,
                'tax_id' => $details['tax_id'] ?? null,
                'billing_address' => $details['billing_address'] ?? null,
            ]);

            CustomerGatewayRef::create([
                'customer_id' => $customer->id,
                'gateway' => $gateway,
                'gateway_customer_id' => $gatewayCustomer->gatewayId,
                'metadata' => null,
            ]);

            $this->snapshotResponse($key, [
                'gateway_customer_id' => $gatewayCustomer->gatewayId,
                'email' => $gatewayCustomer->email,
            ]);

            // Link the idempotency record to the newly-created customer
            // so future audits can trace by customer_id.
            $key->update(['customer_id' => $customer->id]);

            return $customer;
        });

        // Step 6: Dispatch event AFTER commit so listeners see committed rows.
        DB::afterCommit(fn () => event(new CustomerCreated($customer)));

        return $customer;
    }
}
