<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\BillingInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Price: precio concreto de un Plan en una moneda + país + intervalo.
 *
 * Un Plan tiene N Prices. Ejemplo Plan "professional":
 *   - MXN  / mensual / 1399_00 cents
 *   - MXN  / anual   / 13990_00 cents
 *   - USD  / mensual / 79_00 cents
 *   - USD  / anual   / 790_00 cents
 *
 * gateway_refs guarda el ID del Price equivalente en cada pasarela:
 *   {"stripe": "price_NA12345", "mercadopago": "plan_xxx"}
 *
 * @property string $id
 * @property string $plan_id
 * @property string $currency
 * @property string|null $country
 * @property BillingInterval $interval
 * @property int $interval_count
 * @property int $amount_cents
 * @property string $tax_behavior
 * @property array<string, string>|null $gateway_refs
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Price extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_prices';

    protected $fillable = [
        'plan_id',
        'currency',
        'country',
        'interval',
        'interval_count',
        'amount_cents',
        'tax_behavior',
        'gateway_refs',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interval' => BillingInterval::class,
            'interval_count' => 'integer',
            'amount_cents' => 'integer',
            'gateway_refs' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Returns the price formatted in major units (e.g. 1399.00 MXN).
     * For display only — do NOT use for calculations.
     */
    public function formattedAmount(): string
    {
        return number_format($this->amount_cents / 100, 2, '.', ',');
    }

    /**
     * Returns the gateway-specific ID for this price, if registered.
     */
    public function gatewayId(string $gateway): ?string
    {
        return $this->gateway_refs[$gateway] ?? null;
    }

    // ── Scopes ─────────────────────────────────────────────────────

    /**
     * @param  Builder<Price>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Price>  $query
     */
    public function scopeForCurrency(Builder $query, string $currency): Builder
    {
        return $query->where('currency', strtoupper($currency));
    }
}
