<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Billing\DTOs\ResolvedEntitlements;
use App\Models\Tenant;
use App\Services\Billing\EntitlementService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entitlement — Feature value materialized for a single Subscription.
 *
 * One row per (subscription, feature). Values mirror billing_plan_features
 * at the time the subscription was activated, decoupling active subs from
 * later catalog changes.
 *
 * The source column distinguishes how this row got here:
 *   - 'plan'  : copied verbatim from the catalog (default).
 *   - 'grant' : overridden by an operational grant (see EntitlementGrant).
 *
 * @property string $id
 * @property string $subscription_id
 * @property string $feature_id
 * @property int|null $value_numeric
 * @property bool|null $value_boolean
 * @property string|null $value_string
 * @property string|null $reset_period
 * @property string $source
 * @property array<string, mixed>|null $metadata
 */
class Entitlement extends Model
{
    use HasFactory;
    use HasUlids;

    public const SOURCE_PLAN = 'plan';

    public const SOURCE_GRANT = 'grant';

    protected $table = 'billing_entitlements';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'plan',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'feature_id',
        'value_numeric',
        'value_boolean',
        'value_string',
        'reset_period',
        'source',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_numeric' => 'integer',
            'value_boolean' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Resolve the effective entitlements for a tenant.
     *
     * Facade over EntitlementService (per SPEC §4): the product calls
     * Entitlement::for($tenant)->has('whitelabel.full') and friends,
     * without knowing how subscriptions, grants, and the dual-read
     * fallback are stitched together.
     */
    public static function for(Tenant $tenant): ResolvedEntitlements
    {
        return app(EntitlementService::class)->for($tenant);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }
}
