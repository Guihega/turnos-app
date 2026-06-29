<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Events\Billing\CustomerCreated;
use App\Models\Billing\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Creates the billing Customer for a Tenant locally, without touching any
 * payment gateway.
 *
 * This is the customer-side counterpart to CreatePilotSubscriptionAction
 * (PR-S2). It is deliberately NOT CreateCustomerAction, which is the paid
 * path — a Stripe saga that registers the customer in the gateway and
 * writes a CustomerGatewayRef (ADR-016). A pilot tenant is free and is not
 * registered in any gateway, so it gets a local Customer row with no
 * gateway ref; the gateway ref (HasMany) is provisioned later if and when
 * the tenant converts to a paid plan.
 *
 * Billing identity is derived from the Tenant: billing_email from the
 * tenant email, tax_id carried over if present, country defaulting to MX
 * and currency to MXN (the billing_customers schema defaults).
 *
 * Idempotent on the 1:1 tenant_id unique constraint: if the tenant already
 * has a Customer, that one is returned rather than inserting a second and
 * violating the unique index. This makes the backfill safe to re-run and
 * the onboarding hook safe against retries.
 *
 * Dispatches CustomerCreated post-commit (ADR-016 §6).
 *
 * @see App\Actions\Billing\CreateCustomerAction (paid path, with gateway)
 * @see App\Actions\Billing\CreatePilotSubscriptionAction (subscription side)
 */
final class CreatePilotCustomerAction
{
    public function execute(Tenant $tenant): Customer
    {
        $existing = Customer::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($tenant): Customer {
            /** @var Customer $customer */
            $customer = Customer::create([
                'tenant_id' => $tenant->id,
                'country' => 'MX',
                'default_currency' => 'MXN',
                'billing_email' => $tenant->email,
                'billing_name' => $tenant->legal_name ?? $tenant->name,
                'tax_id' => $tenant->tax_id,
                'metadata' => [
                    'created_via' => 'CreatePilotCustomerAction',
                ],
            ]);

            // Post-commit: keep parity with CreateCustomerAction's contract.
            DB::afterCommit(fn () => event(new CustomerCreated($customer)));

            return $customer;
        });
    }
}
