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
use App\Enums\TicketStatus;

class Queue extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'branch_id', 'name', 'prefix', 'description', 'priority_algorithm',
        'max_capacity', 'is_active', 'sort_order', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withPivot('priority')
            ->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function waitingTickets(): HasMany
    {
        return $this->tickets()
            ->where('status', TicketStatus::WAITING)
            ->orderByDesc('priority_score')
            ->orderBy('issued_at');
    }

    public function currentLength(): int
    {
        return $this->waitingTickets()->count();
    }

    public function nextTicket(): ?Ticket
    {
        return $this->waitingTickets()->first();
    }

    public function isFull(): bool
    {
        return $this->currentLength() >= $this->max_capacity;
    }

    /**
     * Scope para filtrar solo colas activas.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
