<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Plan: definición comercial.
 *
 * Identificado por code estable (pilot, starter, professional, ...).
 * Tiene N Prices (uno por moneda/intervalo) y N Features con
 * sus valores específicos en billing_plan_features.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_public
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Plan extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_plans';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_public',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * Features attached to this plan via billing_plan_features.
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'billing_plan_features')
            ->using(PlanFeature::class)
            ->withPivot([
                'value_numeric',
                'value_boolean',
                'value_string',
                'reset_period',
            ])
            ->withTimestamps();
    }

    /**
     * Direct access to PlanFeature pivot rows (when you need the value
     * without going through the Feature relationship).
     */
    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    /**
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }
}
