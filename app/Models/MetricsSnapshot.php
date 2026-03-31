<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricsSnapshot extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id', 'queue_id', 'service_id', 'date', 'hour', 'granularity',
        'tickets_issued', 'tickets_served', 'tickets_cancelled', 'tickets_no_show',
        'tickets_transferred', 'appointments_scheduled', 'appointments_completed',
        'avg_wait_time', 'max_wait_time', 'min_wait_time',
        'p50_wait_time', 'p90_wait_time', 'p95_wait_time',
        'avg_service_time', 'max_service_time', 'min_service_time',
        'peak_queue_length', 'avg_queue_length', 'active_operators',
        'avg_rating', 'ratings_count', 'sla_compliance_pct',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'avg_rating' => 'decimal:2',
            'sla_compliance_pct' => 'decimal:2',
        ];
    }

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

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeDaily($query)
    {
        return $query->where('granularity', 'daily');
    }

    public function scopeHourly($query)
    {
        return $query->where('granularity', 'hourly');
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
