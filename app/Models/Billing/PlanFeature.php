<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * PlanFeature: pivot enriquecido entre Plan y Feature.
 *
 * Define el VALOR concreto que un Plan otorga para una Feature.
 * Solo se llena el value_* correspondiente al type de la Feature
 * (boolean → value_boolean, quota/metered → value_numeric, string → value_string).
 *
 * Convención para quotas:
 *   value_numeric = -1  → ilimitado
 *   value_numeric = 0   → no permitido
 *   value_numeric > 0   → límite exacto
 *
 * @property string $id
 * @property string $plan_id
 * @property string $feature_id
 * @property int|null $value_numeric
 * @property bool|null $value_boolean
 * @property string|null $value_string
 * @property string|null $reset_period
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PlanFeature extends Pivot
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_plan_features';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = [
        'plan_id',
        'feature_id',
        'value_numeric',
        'value_boolean',
        'value_string',
        'reset_period',
    ];

    protected function casts(): array
    {
        return [
            'value_numeric' => 'integer',
            'value_boolean' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Convención: value_numeric = -1 representa ilimitado.
     */
    public function isUnlimited(): bool
    {
        return $this->value_numeric === -1;
    }
}
