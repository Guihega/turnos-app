<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorMetric extends Model
{
    use HasUlids;

    protected $table = 'operator_metrics';

    protected $fillable = [
        'user_id', 'branch_id', 'date', 'tickets_served',
        'avg_service_time', 'total_service_time', 'total_idle_time',
        'avg_rating', 'ratings_count', 'breaks_taken',
        'break_duration_seconds', 'utilization_pct',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'avg_rating' => 'decimal:2',
            'utilization_pct' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
