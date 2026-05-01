<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_number' => $this->display_number,
            'ticket_number' => $this->ticket_number,

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'is_active' => $this->status->isActive(),
            ],

            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
                'score' => $this->priority_score,
            ],

            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->when($request->user()?->hasPermission('tickets.view'), $this->customer_phone),
                'email' => $this->when($request->user()?->hasPermission('tickets.view'), $this->customer_email),
            ],

            'queue' => $this->when($this->relationLoaded('queue'), fn () => [
                'id' => $this->queue->id,
                'name' => $this->queue->name,
                'prefix' => $this->queue->prefix,
            ]),

            'service' => $this->when($this->relationLoaded('service'), fn () => [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'color' => $this->service->color,
                'estimated_minutes' => $this->service->estimated_duration_minutes,
            ]),

            'counter' => $this->when($this->relationLoaded('counter') && $this->counter, fn () => [
                'id' => $this->counter->id,
                'name' => $this->counter->name,
                'number' => $this->counter->number,
            ]),

            'operator' => $this->when($this->relationLoaded('servedBy') && $this->servedBy, fn () => [
                'id' => $this->servedBy->id,
                'name' => $this->servedBy->name,
                'avatar_url' => $this->servedBy->avatar_url,
            ]),

            'timestamps' => [
                'issued_at' => $this->issued_at?->toIso8601String(),
                'called_at' => $this->called_at?->toIso8601String(),
                'started_at' => $this->started_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            ],

            'metrics' => [
                'wait_time_seconds' => $this->wait_time_seconds,
                'service_time_seconds' => $this->service_time_seconds,
                'total_time_seconds' => $this->total_time_seconds,
                'position_in_queue' => $this->when($this->isActive(), fn () => $this->positionInQueue()),
                'estimated_wait_minutes' => $this->when($this->isActive(), fn () => $this->estimatedWaitMinutes()),
            ],

            'feedback' => [
                'rating' => $this->rating,
                'feedback' => $this->when($request->user()?->hasPermission('tickets.view'), $this->feedback),
            ],

            'transfer_count' => $this->transfer_count,
            'notes' => $this->when($request->user()?->hasPermission('tickets.view'), $this->notes),

            'events' => $this->when($this->relationLoaded('events'), fn () => $this->events->map(fn ($e) => [
                'type' => $e->event_type,
                'from' => $e->from_status,
                'to' => $e->to_status,
                'at' => $e->occurred_at->toIso8601String(),
                'user' => $e->user?->name,
            ])
            ),

            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
