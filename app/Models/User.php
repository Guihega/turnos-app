<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Models\BranchUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'password', 'role',
        'avatar_url', 'locale', 'preferences', 'is_active',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->using(BranchUser::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function servedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'served_by');
    }

    public function operatorMetrics(): HasMany
    {
        return $this->hasMany(OperatorMetric::class);
    }

    // ── Authorization Helpers ──

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === UserRole::TENANT_ADMIN;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->role->hasPermission($permission);
    }

    public function belongsToBranch(string $branchId): bool
    {
        if ($this->isSuperAdmin() || $this->isTenantAdmin()) {
            return true;
        }

        return $this->branches()
            ->where('branches.id', $branchId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function roleInBranch(string $branchId): ?UserRole
    {
        $pivot = $this->branches()
            ->where('branches.id', $branchId)
            ->first()?->pivot;

        return $pivot ? UserRole::from($pivot->role) : null;
    }

    // ── Scopes ──

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOperators($query)
    {
        return $query->where('role', UserRole::OPERATOR);
    }
}
