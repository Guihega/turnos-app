<?php

declare(strict_types=1);

namespace App\Events\Billing;

use App\Models\Billing\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a Customer has been created in both the gateway
 * and the local DB (post-commit). Listeners may safely query the
 * Customer and its CustomerGatewayRef.
 *
 * @see App\Actions\Billing\CreateCustomerAction
 * @see docs/adr/ADR-016-billing-write-contract-and-create-flows.md
 */
final class CustomerCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Customer $customer) {}
}
