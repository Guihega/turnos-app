<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'code', 'address', 'city', 'state',
        'country', 'zip_code', 'latitude', 'longitude', 'phone', 'email',
        'timezone', 'operating_hours', 'settings', 'max_daily_tickets',
        'max_concurrent_waiting', 'is_active', 'accepts_walkins', 'accepts_appointments',
    ];

    protected function casts(): array
    {
        return [
            'operating_hours' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'accepts_walkins' => 'boolean',
            'accepts_appointments' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }

    public function counters(): HasMany
    {
        return $this->hasMany(Counter::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function metricsSnapshots(): HasMany
    {
        return $this->hasMany(MetricsSnapshot::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ── Helpers ──

    public function isOpen(): bool
    {
        if (!$this->operating_hours) {
            return true;
        }

        $now = now($this->timezone);
        $day = strtolower($now->format('D'));
        $hours = $this->operating_hours[$day] ?? null;

        if (!$hours || !isset($hours['open'], $hours['close'])) {
            return false;
        }

        $open = $now->copy()->setTimeFromTimeString($hours['open']);
        $close = $now->copy()->setTimeFromTimeString($hours['close']);

        return $now->between($open, $close);
    }

    public function todayTicketCount(): int
    {
        return $this->tickets()
            ->whereDate('created_at', today($this->timezone))
            ->count();
    }

    public function canIssueTicket(): bool
    {
        return $this->is_active
            && $this->isOpen()
            && $this->todayTicketCount() < $this->max_daily_tickets;
    }

    public function activeWaitingCount(): int
    {
        return $this->tickets()
            ->whereIn('status', ['waiting', 'called'])
            ->count();
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
