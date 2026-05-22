<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Billing\Customer;
use App\Models\Billing\EntitlementGrant;
use App\Models\Billing\Subscription;
use App\Models\Concerns\HasTenantSettings;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 */
class Tenant extends Model
{
    use HasFactory, HasTenantSettings, HasUlids, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'legal_name', 'tax_id', 'email', 'phone',
        'logo_url', 'timezone', 'locale', 'settings', 'is_active',
        'trial_ends_at', 'plan',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function activeBranches(): HasMany
    {
        return $this->branches()->where('is_active', true);
    }

    public function entitlementGrants(): HasMany
    {
        return $this->hasMany(EntitlementGrant::class, 'tenant_id');
    }

    /**
     * The single billing subscription for this tenant, via its Customer.
     *
     * Structural 1:1:1 chain (Tenant -> Customer -> Subscription); both
     * hops are enforced UNIQUE in the schema (billing_customers.tenant_id,
     * billing_subscriptions.customer_id). Whether the subscription grants
     * feature access is a domain decision evaluated by EntitlementService
     * via SubscriptionStatus::grantsAccess(), not filtered here.
     */
    public function subscription(): HasOneThrough
    {
        return $this->hasOneThrough(
            Subscription::class,
            Customer::class,
            'tenant_id',    // FK on billing_customers -> tenants.id
            'customer_id',  // FK on billing_subscriptions -> billing_customers.id
            'id',           // local key on tenants
            'id',           // local key on billing_customers
        );
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ──

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }
}
