<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\Gateway;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * CustomerGatewayRef: ID externo de un Customer en una pasarela.
 *
 * Un mismo Customer puede tener N refs (una por cada pasarela
 * en la que existe). Esto permite que el Tenant pueda pagar
 * en distintas monedas/pasarelas sin duplicar identidad.
 *
 * @property string $id
 * @property string $customer_id
 * @property Gateway $gateway
 * @property string $gateway_customer_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomerGatewayRef extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_customer_gateway_refs';

    protected $fillable = [
        'customer_id',
        'gateway',
        'gateway_customer_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gateway' => Gateway::class,
            'metadata' => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
