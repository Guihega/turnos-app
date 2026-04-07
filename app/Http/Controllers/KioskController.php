<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\IssueTicketAction;
use App\Actions\IssueTicketData;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Ticket;
use Illuminate\Http\Request;
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
            ->where('status', TicketStatus::WAITING)->count();

        $avgWait = (int) Ticket::where('branch_id', $branch->id)
            ->where('status', TicketStatus::COMPLETED)->whereDate('created_at', today())
            ->whereNotNull('wait_time_seconds')->avg('wait_time_seconds');

        return Inertia::render('Public/Kiosk', [
            'branch' => [
                'id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code,
                'is_open' => true, // TODO: restore $branch->isOpen() for production
                'accepts_walkins' => $branch->accepts_walkins ?? true,
            ],
            'services' => $services,
            'waitingCount' => $waitingCount,
            'avgWaitMinutes' => (int) round($avgWait / 60),
        ]);
    }

    /**
     * Issue ticket — delegates to IssueTicketAction for consistent business logic.
     */
    public function store(Request $request, Branch $branch, IssueTicketAction $action)
    {
        $request->validate([
            'service_id'     => 'required|exists:services,id',
            'queue_id'       => 'required|exists:queues,id',
            'customer_name'  => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        try {
            $data = new IssueTicketData(
                branchId: $branch->id,
                queueId: $request->input('queue_id'),
                serviceId: $request->input('service_id'),
                customerName: $request->input('customer_name'),
                customerPhone: $request->input('customer_phone'),
            );

            $ticket = $action->execute($data);

            return redirect()->route('kiosk.status', ['branch' => $branch->id, 'ticket' => $ticket->id]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['branch' => $e->getMessage()]);
        }
    }

    public function status(Request $request, Branch $branch, Ticket $ticket): Response
    {
        $ticket->load(['service:id,name', 'queue:id,name', 'counter:id,number']);
        $statusValue = $ticket->status->value;
        $isActive = in_array($statusValue, ['waiting', 'called', 'in_progress']);

        $newTicket = null;
        if ($statusValue === 'transferred') {
            $replacement = Ticket::with(['service:id,name', 'queue:id,name', 'counter:id,number'])
                ->where('transferred_from_id', $ticket->id)->first();

            if ($replacement) {
                $rActive = in_array($replacement->status->value, ['waiting', 'called', 'in_progress']);
                $newTicket = [
                    'id' => $replacement->id, 'display_number' => $replacement->display_number,
                    'status' => $replacement->status->value, 'status_label' => $replacement->status->label(),
                    'queue_name' => $replacement->queue?->name,
                    'counter_number' => $replacement->counter?->number,
                    'position' => $rActive ? $replacement->positionInQueue() : null,
                    'url' => route('kiosk.status', ['branch' => $branch->id, 'ticket' => $replacement->id]),
                ];
            }
        }

        return Inertia::render('Public/TicketStatus', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'ticket' => [
                'id' => $ticket->id, 'display_number' => $ticket->display_number,
                'customer_name' => $ticket->customer_name,
                'status' => $statusValue, 'status_label' => $ticket->status->label(),
                'service_name' => $ticket->service?->name, 'queue_name' => $ticket->queue?->name,
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
