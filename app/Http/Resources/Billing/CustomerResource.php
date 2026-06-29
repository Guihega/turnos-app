<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Models\Billing\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country' => $this->country,
            'default_currency' => $this->default_currency,
            'billing_email' => $this->billing_email,
            'billing_name' => $this->billing_name,
            'billing_address' => $this->billing_address,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
