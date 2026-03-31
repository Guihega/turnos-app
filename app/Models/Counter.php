<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Counter extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'branch_id', 'name', 'number', 'current_operator_id',
        'current_ticket_id', 'status', 'serves_queues',
    ];

    protected function casts(): array
    {
        return [
            'serves_queues' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function currentOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_operator_id');
    }

    public function currentTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'current_ticket_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'open' && $this->current_ticket_id === null;
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeForOperator($query, string $userId)
    {
        return $query->where('current_operator_id', $userId);
    }
}
