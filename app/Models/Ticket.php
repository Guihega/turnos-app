<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Ticket extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'branch_id', 'queue_id', 'service_id', 'counter_id', 'served_by',
        'created_by', 'appointment_id', 'ticket_number', 'daily_sequence',
        'display_number', 'customer_name', 'customer_phone', 'customer_email',
        'customer_id_number', 'status', 'priority', 'priority_score',
        'issued_at', 'called_at', 'started_at', 'completed_at', 'cancelled_at',
        'transferred_at', 'wait_time_seconds', 'service_time_seconds',
        'total_time_seconds', 'transferred_from_id', 'transfer_count',
        'rating', 'feedback', 'metadata', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'issued_at' => 'datetime',
            'called_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'transferred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ── Relationships ──

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->orderBy('occurred_at');
    }

    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transferred_from_id');
    }

    // ── State Machine ──

    public function transitionTo(TicketStatus $newStatus, ?string $userId = null): self
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition from {$this->status->value} to {$newStatus->value}"
            );
        }

        $oldStatus = $this->status;
        $now = now();

        $this->status = $newStatus;

        match ($newStatus) {
            TicketStatus::CALLED => $this->called_at = $now,
            TicketStatus::IN_PROGRESS => $this->handleStarted($now),
            TicketStatus::COMPLETED => $this->handleCompleted($now),
            TicketStatus::CANCELLED => $this->cancelled_at = $now,
            TicketStatus::TRANSFERRED => $this->transferred_at = $now,
            TicketStatus::NO_SHOW => $this->cancelled_at = $now,
            default => null,
        };

        $this->save();

        // Record event for audit trail
        $this->events()->create([
            'user_id' => $userId,
            'event_type' => "status_changed",
            'from_status' => $oldStatus->value,
            'to_status' => $newStatus->value,
            'occurred_at' => $now,
        ]);

        return $this;
    }

    private function handleStarted(\DateTimeInterface $now): void
    {
        $this->started_at = $now;

        if ($this->issued_at) {
            $this->wait_time_seconds = (int) $this->issued_at->diffInSeconds($now);
        }
    }

    private function handleCompleted(\DateTimeInterface $now): void
    {
        $this->completed_at = $now;

        if ($this->started_at) {
            $this->service_time_seconds = (int) $this->started_at->diffInSeconds($now);
        }

        if ($this->issued_at) {
            $this->total_time_seconds = (int) $this->issued_at->diffInSeconds($now);
        }
    }

    // ── Scopes ──

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            TicketStatus::WAITING,
            TicketStatus::CALLED,
            TicketStatus::IN_PROGRESS,
        ]);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', TicketStatus::WAITING);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeByPriority($query)
    {
        return $query->orderByDesc('priority_score')->orderBy('issued_at');
    }

    // ── Helpers ──

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function estimatedWaitMinutes(): int
    {
        if (!$this->isActive()) {
            return 0;
        }

        $positionInQueue = self::where('branch_id', $this->branch_id)
            ->where('queue_id', $this->queue_id)
            ->where('status', TicketStatus::WAITING)
            ->where('priority_score', '>=', $this->priority_score)
            ->where('issued_at', '<', $this->issued_at)
            ->count();

        $avgServiceTime = $this->service?->estimated_duration_minutes ?? 15;

        return $positionInQueue * $avgServiceTime;
    }

    public function positionInQueue(): int
    {
        return self::where('branch_id', $this->branch_id)
            ->where('queue_id', $this->queue_id)
            ->where('status', TicketStatus::WAITING)
            ->where(function ($q) {
                $q->where('priority_score', '>', $this->priority_score)
                    ->orWhere(function ($q2) {
                        $q2->where('priority_score', $this->priority_score)
                            ->where('issued_at', '<', $this->issued_at);
                    });
            })
            ->count() + 1;
    }
}
