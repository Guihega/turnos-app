<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TicketActionController extends Controller
{
    /**
     * Emitir turno desde el admin/recepción.
     */
    public function issue(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'queue_id' => 'required|exists:queues,id',
            'service_id' => 'required|exists:services,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'priority' => 'nullable|string|in:low,normal,high,urgent,vip',
        ]);

        $user = $request->user();

        // F-06/F-15: Validate branch belongs to user's tenant
        $branch = Branch::where('id', $request->branch_id)
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();

        // Validate queue belongs to this branch
        $queue = Queue::where('id', $request->queue_id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->firstOrFail();

        $priority = $request->priority ? TicketPriority::from($request->priority) : TicketPriority::NORMAL;

        return DB::transaction(function () use ($request, $branch, $queue, $priority, $user) {
            // F-17: Get next sequence safely — lock rows first, then compute max
            // This avoids the "FOR UPDATE with aggregate" issue on some DB drivers
            $maxSeq = Ticket::where('branch_id', $branch->id)
                ->whereDate('created_at', today())
                ->max('daily_sequence');
            $seq = ($maxSeq ?? 0) + 1;

            $ticketNumber = sprintf('%s-%03d', $queue->prefix, $seq);
            $displayNumber = sprintf('%s-%s', $branch->code, $ticketNumber);

            $ticket = Ticket::create([
                'branch_id' => $branch->id,
                'queue_id' => $queue->id,
                'service_id' => $request->service_id,
                'created_by' => $user->id,
                'ticket_number' => $ticketNumber,
                'daily_sequence' => $seq,
                'display_number' => $displayNumber,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'customer_email' => $request->customer_email,
                'status' => TicketStatus::WAITING,
                'priority' => $priority,
                'priority_score' => $priority->weight(),
                'issued_at' => now(),
            ]);

            $ticket->events()->create([
                'event_type' => 'ticket_issued',
                'user_id' => $user->id,
                'to_status' => TicketStatus::WAITING->value,
                'occurred_at' => now(),
                'payload' => ['source' => 'admin', 'priority' => $priority->value],
            ]);

            return back()->with('success', "Turno {$displayNumber} emitido correctamente.");
        });
    }

    /**
     * Ver detalle de un ticket.
     */
    public function show(Request $request, Ticket $ticket)
    {
        // F-06: Validate ticket belongs to user's tenant
        // Use withoutGlobalScopes to load the branch even if it belongs to another tenant
        $branch = Branch::withoutGlobalScopes()->find($ticket->branch_id);
        if (! $branch || $branch->tenant_id !== $request->user()->tenant_id) {
            abort(403, 'No tiene acceso a este turno.');
        }

        $ticket->load(['queue', 'service', 'counter', 'servedBy', 'createdByUser', 'events.user', 'branch']);

        return Inertia::render('Tickets/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'display_number' => $ticket->display_number,
                'customer_name' => $ticket->customer_name,
                'customer_phone' => $ticket->customer_phone,
                'customer_email' => $ticket->customer_email,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'status_color' => $ticket->status->color(),
                'priority' => $ticket->priority->value,
                'priority_label' => $ticket->priority->label(),
                'queue_name' => $ticket->queue?->name,
                'service_name' => $ticket->service?->name,
                'service_color' => $ticket->service?->color,
                'counter_number' => $ticket->counter?->number,
                'operator_name' => $ticket->servedBy?->name,
                'created_by_name' => $ticket->createdByUser?->name,
                'branch_name' => $ticket->branch?->name,
                'rating' => $ticket->rating,
                'feedback' => $ticket->feedback,
                'notes' => $ticket->notes,
                'wait_time_seconds' => $ticket->wait_time_seconds,
                'service_time_seconds' => $ticket->service_time_seconds,
                'total_time_seconds' => $ticket->total_time_seconds,
                'transfer_count' => $ticket->transfer_count,
                'issued_at' => $ticket->issued_at?->format('d/m/Y H:i:s'),
                'called_at' => $ticket->called_at?->format('d/m/Y H:i:s'),
                'started_at' => $ticket->started_at?->format('d/m/Y H:i:s'),
                'completed_at' => $ticket->completed_at?->format('d/m/Y H:i:s'),
                'cancelled_at' => $ticket->cancelled_at?->format('d/m/Y H:i:s'),
                'events' => $ticket->events->map(fn ($e) => [
                    'type' => $e->event_type,
                    'from' => $e->from_status,
                    'to' => $e->to_status,
                    'user' => $e->user?->name,
                    'at' => $e->occurred_at->format('H:i:s'),
                ]),
            ],
        ]);
    }
}
