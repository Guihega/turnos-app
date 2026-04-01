<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketIssued;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KioskController extends Controller
{
    public function index(Request $request, Branch $branch): Response
    {
        $queues = Queue::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->with(['services' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->get();

        $services = $queues->flatMap(function ($queue) {
            return $queue->services->map(fn($s) => [
                'id' => $s->id, 'name' => $s->name, 'code' => $s->code, 'color' => $s->color,
                'icon' => $s->icon, 'description' => $s->description,
                'estimated_minutes' => $s->estimated_duration_minutes,
                'queue_id' => $queue->id, 'queue_name' => $queue->name, 'queue_prefix' => $queue->prefix,
            ]);
        })->unique('id')->values();

        $waitingCount = Ticket::where('branch_id', $branch->id)
            ->where('status', TicketStatus::WAITING)->whereDate('created_at', today())->count();

        $avgWait = (int) Ticket::where('branch_id', $branch->id)
            ->where('status', TicketStatus::COMPLETED)->whereDate('created_at', today())
            ->whereNotNull('wait_time_seconds')->avg('wait_time_seconds');

        return Inertia::render('Public/Kiosk', [
            'branch' => [
                'id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code,
                'is_open' => method_exists($branch, 'isOpen') ? $branch->isOpen() : true,
                'accepts_walkins' => $branch->accepts_walkins ?? true,
            ],
            'services' => $services,
            'waitingCount' => $waitingCount,
            'avgWaitMinutes' => (int) round($avgWait / 60),
        ]);
    }

    public function store(Request $request, Branch $branch)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'queue_id' => 'required|exists:queues,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $queue = Queue::findOrFail($request->queue_id);

        return DB::transaction(function () use ($request, $branch, $queue) {
            $seq = Ticket::where('branch_id', $branch->id)->whereDate('created_at', today())->max('daily_sequence') + 1;
            $ticketNumber = sprintf('%s-%03d', $queue->prefix, $seq);
            $displayNumber = sprintf('%s-%s', $branch->code, $ticketNumber);

            $ticket = Ticket::create([
                'branch_id' => $branch->id, 'queue_id' => $queue->id, 'service_id' => $request->service_id,
                'ticket_number' => $ticketNumber, 'daily_sequence' => $seq, 'display_number' => $displayNumber,
                'customer_name' => $request->customer_name, 'customer_phone' => $request->customer_phone,
                'status' => TicketStatus::WAITING, 'priority' => TicketPriority::NORMAL,
                'priority_score' => TicketPriority::NORMAL->weight(), 'issued_at' => now(),
            ]);

            $ticket->events()->create([
                'event_type' => 'ticket_issued', 'to_status' => TicketStatus::WAITING->value,
                'occurred_at' => now(), 'payload' => ['source' => 'kiosk'],
            ]);

            try { TicketIssued::dispatch($ticket->load(['queue', 'service', 'branch'])); } catch (\Throwable $e) {}

            return redirect()->route('kiosk.status', ['branch' => $branch->id, 'ticket' => $ticket->id]);
        });
    }

    public function status(Request $request, Branch $branch, Ticket $ticket): Response
    {
        $ticket->load(['service:id,name', 'queue:id,name', 'counter:id,number']);
        $statusValue = $ticket->status->value;
        $isActive = in_array($statusValue, ['waiting', 'called', 'in_progress']);

        // If transferred, find the new ticket that replaced this one
        $newTicket = null;
        if ($statusValue === 'transferred') {
            $replacement = Ticket::with(['service:id,name', 'queue:id,name', 'counter:id,number'])
                ->where('transferred_from_id', $ticket->id)
                ->first();

            if ($replacement) {
                $replacementStatus = $replacement->status->value;
                $replacementActive = in_array($replacementStatus, ['waiting', 'called', 'in_progress']);

                $newTicket = [
                    'id' => $replacement->id,
                    'display_number' => $replacement->display_number,
                    'status' => $replacementStatus,
                    'status_label' => $replacement->status->label(),
                    'queue_name' => $replacement->queue?->name,
                    'service_name' => $replacement->service?->name,
                    'counter_number' => $replacement->counter?->number,
                    'position' => $replacementActive ? $replacement->positionInQueue() : null,
                    'estimated_wait_minutes' => $replacementActive ? $replacement->estimatedWaitMinutes() : null,
                    'url' => route('kiosk.status', ['branch' => $branch->id, 'ticket' => $replacement->id]),
                ];
            }
        }

        return Inertia::render('Public/TicketStatus', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'ticket' => [
                'id' => $ticket->id,
                'display_number' => $ticket->display_number,
                'customer_name' => $ticket->customer_name,
                'status' => $statusValue,
                'status_label' => $ticket->status->label(),
                'status_color' => $ticket->status->color(),
                'service_name' => $ticket->service?->name,
                'queue_name' => $ticket->queue?->name,
                'counter_number' => $ticket->counter?->number,
                'position' => $isActive ? $ticket->positionInQueue() : null,
                'estimated_wait_minutes' => $isActive ? $ticket->estimatedWaitMinutes() : null,
                'issued_at' => $ticket->issued_at?->toIso8601String(),
                'called_at' => $ticket->called_at?->toIso8601String(),
                'completed_at' => $ticket->completed_at?->toIso8601String(),
                'new_ticket' => $newTicket,
            ],
        ]);
    }
}
