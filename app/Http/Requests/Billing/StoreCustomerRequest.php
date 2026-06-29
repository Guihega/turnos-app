<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\Billing\BillingPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Input validation + authorization for POST /api/v1/billing/customers.
 *
 * The endpoint creates a Customer for the authenticated user's tenant.
 * The tenant is implicit (resolved from $user->tenant), so the request
 * body does NOT carry tenant_id.
 */
final class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        /** @var Tenant|null $tenant */
        $tenant = $user->tenant;
        if ($tenant === null) {
            return false;
        }

        return (new BillingPolicy)->createCustomer($user, $tenant);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'billing_email' => ['required', 'email:rfc', 'max:255'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'default_currency' => ['required', 'string', 'size:3'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'billing_address' => ['nullable', 'array'],
            'billing_address.street' => ['nullable', 'string', 'max:255'],
            'billing_address.street2' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['nullable', 'string', 'max:128'],
            'billing_address.state' => ['nullable', 'string', 'max:128'],
            'billing_address.zip' => ['nullable', 'string', 'max:32'],
            'billing_address.country' => ['nullable', 'string', 'size:2'],
        ];
    }
}
