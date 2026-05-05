<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Customer: representación del Tenant en el contexto Billing.
 *
 * 1:1 con Tenant. Contiene la identidad de facturación
 * (email, nombre fiscal, RFC, dirección) y la lista de
 * IDs externos en cada pasarela (CustomerGatewayRef).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $country
 * @property string $default_currency
 * @property string $billing_email
 * @property string|null $billing_name
 * @property string|null $tax_id
 * @property array<string, mixed>|null $billing_address
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Customer extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'billing_customers';

    protected $fillable = [
        'tenant_id',
        'country',
        'default_currency',
        'billing_email',
        'billing_name',
        'tax_id',
        'billing_address',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'billing_address' => 'array',
            'metadata' => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function gatewayRefs(): HasMany
    {
        return $this->hasMany(CustomerGatewayRef::class);
    }
}
