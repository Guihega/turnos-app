<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Models\BranchUser;
use App\Models\SocialAccount;
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
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedRow;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;

class User extends Authenticatable implements MustVerifyEmail, CipherSweetEncrypted
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes, UsesCipherSweet;

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

    /**
     * Configure CipherSweet field-level encryption.
     *
     * Encrypted fields:
     *  - email: with blind index for login lookups and uniqueness validation
     *  - phone: optional (nullable), no search needed
     *  - last_login_ip: optional (nullable), no search needed
     *
     * Note: 'name' is intentionally NOT encrypted to preserve LIKE search
     * capability in the admin user management panel. Name alone does not
     * constitute a unique identifier without email/phone context.
     */
    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        $encryptedRow
            ->addField('email')
            ->addOptionalTextField('phone')
            ->addOptionalTextField('last_login_ip')
            ->addBlindIndex('email', new BlindIndex('email_index'));
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

    /**
     * Cuentas sociales vinculadas (Google, Facebook).
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
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

    /**
     * Verificar si el usuario tiene vinculada una cuenta de un provider.
     */
    public function hasSocialAccount(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
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

    /**
     * Find a user by their email using the blind index.
     *
     * This replaces any User::where('email', $email)->first() calls
     * since the email column is now encrypted and cannot be searched directly.
     */
    public static function findByEmail(string $email): ?self
    {
        return static::whereBlind('email', 'email_index', $email)->first();
    }
}
