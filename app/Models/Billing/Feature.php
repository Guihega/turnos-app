<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\FeatureType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Feature: capacidad del producto identificada por código.
 *
 * Las Features son neutras: su VALOR lo asigna cada Plan en
 * billing_plan_features. Lo que el código del producto consulta
 * en runtime son ENTITLEMENTS (tabla billing_entitlements,
 * que se crea en una fase posterior).
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property FeatureType $type
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Feature extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_features';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => FeatureType::class,
            'metadata' => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'billing_plan_features')
            ->using(PlanFeature::class)
            ->withPivot([
                'value_numeric',
                'value_boolean',
                'value_string',
                'reset_period',
            ])
            ->withTimestamps();
    }
}
