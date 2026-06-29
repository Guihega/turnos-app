<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EntitlementGrant — operational override of an entitlement, scoped to a Tenant.
 *
 * Sits on top of plan-derived entitlements (billing_entitlements). The
 * EntitlementService resolves the effective value by taking the plan
 * entitlement and applying any active grant for the same (tenant, feature).
 *
 * Grants are append-only history: one (tenant, feature) pair may have
 * multiple rows over time. The "active" subset is filtered by:
 *   - revoked_at IS NULL
 *   - expires_at IS NULL OR expires_at > now()
 *
 * The first predicate is indexed (partial index entitlement_grants_active);
 * the second is applied in the query for temporal filtering.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $feature_id
 * @property int|null $value_numeric
 * @property bool|null $value_boolean
 * @property string|null $value_string
 * @property string|null $granted_by
 * @property string $reason
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property array<string, mixed>|null $metadata
 *
 * @method static \Illuminate\Database\Eloquent\Builder<EntitlementGrant> active()
 */
class EntitlementGrant extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_entitlement_grants';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'feature_id',
        'value_numeric',
        'value_boolean',
        'value_string',
        'granted_by',
        'reason',
        'expires_at',
        'revoked_at',
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
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Scope: only currently active grants.
     *
     * Active = not revoked and either perpetual (no expiry) or expiry in the future.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
