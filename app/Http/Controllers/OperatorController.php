<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CallNextTicketAction;
use App\Actions\CompleteTicketAction;
use App\Actions\TransferTicketAction;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperatorController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $branches = ($user->isSuperAdmin() || $user->isTenantAdmin())
            ? Branch::where('tenant_id', $tenantId)->where('is_active', true)->get()
            : Branch::where('is_active', true)
                ->whereHas('users', fn($q) => $q->where('users.id', $user->id))
                ->get();

        $branchId = $request->input('branch_id', $branches->first()?->id);
        $branch = $branches->firstWhere('id', $branchId);

        if (!$branch) {
            return Inertia::render('Operator/Index', [
                'branches' => collect(), 'currentBranch' => null, 'counter' => null,
                'availableCounters' => collect(), 'currentTicket' => null,
                'waitingTickets' => collect(), 'queues' => collect(),
                'allQueues' => collect(), 'myStats' => ['served' => 0, 'avg_service' => 0, 'avg_rating' => null],
                'error' => 'No tiene sucursales asignadas.',
            ]);
        }

        return Inertia::render('Operator/Index', [
            'branches'          => $branches->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'code' => $b->code]),
            'currentBranch'     => ['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code],
            'counter'           => $this->getOperatorCounter($branch, $user),
            'availableCounters' => $this->getAvailableCounters($branch, $user),
            'currentTicket'     => $this->getCurrentTicket($user),
            'waitingTickets'    => $this->getWaitingTickets($branch),
            'queues'            => $this->getQueuesWithCounts($branch),
            'allQueues'         => Queue::where('branch_id', $branch->id)->where('is_active', true)->get(['id', 'name', 'prefix']),
            'myStats'           => $this->getOperatorStats($user),
        ]);
    }

    /**
     * Call next ticket — delegates to CallNextTicketAction.
     */
    public function callNext(Request $request, CallNextTicketAction $action)
    {
        $request->validate([
            'counter_id' => 'required|exists:counters,id',
            'queue_id'   => 'nullable|exists:queues,id',
        ]);

        // F-15: Validate counter belongs to operator's branch/tenant
        $this->ensureCounterBelongsToOperator($request->input('counter_id'), $request->user());

        try {
            $ticket = $action->execute(
                $request->input('counter_id'),
                $request->user()->id,
                $request->input('queue_id'),
            );

            return back()->with('success', "Turno {$ticket->display_number} llamado en ventanilla {$ticket->counter?->number}.");
        } catch (\RuntimeException $e) {
            return back()->with('info', $e->getMessage());
        }
    }

    /**
     * Start serving a called ticket.
     */
    public function startServing(Request $request, Ticket $ticket)
    {
        $this->ensureTicketBelongsToOperator($ticket, $request->user());

        if ($ticket->status !== TicketStatus::CALLED) {
            return back()->withErrors(['ticket' => 'El turno no está en estado llamado.']);
        }

        $ticket->transitionTo(TicketStatus::IN_PROGRESS, $request->user()->id);

        return back()->with('success', "Atención iniciada para {$ticket->display_number}.");
    }

    /**
     * Complete ticket — delegates to CompleteTicketAction.
     */
    public function complete(Request $request, Ticket $ticket, CompleteTicketAction $action)
    {
        $this->ensureTicketBelongsToOperator($ticket, $request->user());

        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'notes'  => 'nullable|string|max:1000',
        ]);

        try {
            $action->execute(
                $ticket->id,
                $request->user()->id,
                $request->integer('rating') ?: null,
                $request->input('notes'),
            );

            return back()->with('success', "Turno {$ticket->display_number} completado.");
        } catch (\RuntimeException $e) {
            return back()->withErrors(['ticket' => $e->getMessage()]);
        }
    }

    /**
     * Cancel ticket.
     */
    public function cancel(Request $request, Ticket $ticket)
    {
        // F-15: Validate ticket belongs to operator's tenant/branch
        $this->ensureTicketInOperatorScope($ticket, $request->user());

        $ticket->transitionTo(TicketStatus::CANCELLED, $request->user()->id);
        $this->freeCounter($ticket);

        return back()->with('success', "Turno {$ticket->display_number} cancelado.");
    }

    /**
     * Mark ticket as no-show.
     */
    public function noShow(Request $request, Ticket $ticket)
    {
        // F-15: Validate ticket belongs to operator's tenant/branch
        $this->ensureTicketInOperatorScope($ticket, $request->user());

        $ticket->transitionTo(TicketStatus::NO_SHOW, $request->user()->id);
        $this->freeCounter($ticket);

        return back()->with('success', "Turno {$ticket->display_number} marcado como no presentado.");
    }

    /**
     * Transfer ticket — delegates to TransferTicketAction.
     */
    public function transfer(Request $request, Ticket $ticket, TransferTicketAction $action)
    {
        $this->ensureTicketBelongsToOperator($ticket, $request->user());

        $request->validate([
            'target_queue_id' => 'required|exists:queues,id',
            'reason'          => 'nullable|string|max:500',
        ]);

        // F-15: Validate target queue belongs to same branch
        $targetQueue = Queue::findOrFail($request->input('target_queue_id'));
        if ($targetQueue->branch_id !== $ticket->branch_id) {
            return back()->withErrors(['ticket' => 'La cola destino no pertenece a la misma sucursal.']);
        }

        try {
            $newTicket = $action->execute(
                $ticket->id,
                $request->input('target_queue_id'),
                $request->user()->id,
                $request->input('reason'),
            );

            return back()->with('success', "Turno {$ticket->display_number} transferido → {$newTicket->display_number}");
        } catch (\RuntimeException $e) {
            return back()->withErrors(['ticket' => $e->getMessage()]);
        }
    }

    /**
     * Re-call a ticket (audio notification on display).
     */
    public function recall(Request $request, Ticket $ticket)
    {
        // F-15: Validate ticket belongs to operator
        $this->ensureTicketBelongsToOperator($ticket, $request->user());

        if ($ticket->status !== TicketStatus::CALLED) {
            return back()->withErrors(['ticket' => 'Solo se pueden rellamar turnos en estado "llamado".']);
        }

        try {
            \App\Events\TicketCalled::dispatch($ticket->load(['queue', 'service', 'counter', 'servedBy']));
        } catch (\Throwable) {}

        return back()->with('success', "Turno {$ticket->display_number} rellamado.");
    }

    // ── Private helpers ──

    /**
     * F-15: Ensure ticket is assigned to this operator (for actions requiring ownership).
     */
    private function ensureTicketBelongsToOperator(Ticket $ticket, $user): void
    {
        if ($ticket->served_by !== $user->id) {
            abort(403, 'Este turno no está asignado a usted.');
        }
    }

    /**
     * F-15: Ensure ticket belongs to a branch the operator has access to.
     * Used for cancel/no-show which don't require the ticket to be assigned to the operator
     * but must belong to the same tenant and an accessible branch.
     */
    private function ensureTicketInOperatorScope(Ticket $ticket, $user): void
    {
        // Load branch to check tenant
        $ticket->loadMissing('branch');

        if (!$ticket->branch || $ticket->branch->tenant_id !== $user->tenant_id) {
            abort(403, 'No tiene acceso a este turno.');
        }

        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->belongsToBranch($ticket->branch_id)) {
            abort(403, 'No tiene acceso a esta sucursal.');
        }
    }

    /**
     * F-15: Ensure counter belongs to operator's accessible branches.
     */
    private function ensureCounterBelongsToOperator(string $counterId, $user): void
    {
        $counter = Counter::findOrFail($counterId);
        $counter->loadMissing('branch');

        if (!$counter->branch || $counter->branch->tenant_id !== $user->tenant_id) {
            abort(403, 'No tiene acceso a esta ventanilla.');
        }

        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->belongsToBranch($counter->branch_id)) {
            abort(403, 'No tiene acceso a esta sucursal.');
        }
    }

    private function freeCounter(Ticket $ticket): void
    {
        if ($ticket->counter_id) {
            Counter::where('id', $ticket->counter_id)->update([
                'current_ticket_id' => null,
                'status' => 'open',
            ]);
        }
    }

    private function getOperatorCounter(Branch $branch, $user): ?array
    {
        $counter = Counter::where('branch_id', $branch->id)
            ->where('current_operator_id', $user->id)
            ->first();

        return $counter ? ['id' => $counter->id, 'name' => $counter->name, 'number' => $counter->number] : null;
    }

    private function getAvailableCounters(Branch $branch, $user)
    {
        return Counter::where('branch_id', $branch->id)
            ->where(fn($q) => $q->whereNull('current_operator_id')->orWhere('current_operator_id', $user->id))
            ->orderBy('number')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id, 'name' => $c->name, 'number' => $c->number,
                'status' => $c->status, 'is_mine' => $c->current_operator_id === $user->id,
            ]);
    }

    private function getCurrentTicket($user): ?array
    {
        $ticket = Ticket::with(['queue:id,name,prefix', 'service:id,name,color', 'counter:id,name,number'])
            ->where('served_by', $user->id)
            ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_PROGRESS])
            ->first();

        if (!$ticket) return null;

        return [
            'id' => $ticket->id, 'display_number' => $ticket->display_number,
            'customer_name' => $ticket->customer_name, 'customer_phone' => $ticket->customer_phone,
            'status' => $ticket->status->value, 'status_label' => $ticket->status->label(),
            'priority' => $ticket->priority->value, 'priority_label' => $ticket->priority->label(),
            'queue_id' => $ticket->queue_id, 'queue_name' => $ticket->queue?->name,
            'queue_prefix' => $ticket->queue?->prefix,
            'service_name' => $ticket->service?->name, 'service_color' => $ticket->service?->color,
            'counter_number' => $ticket->counter?->number,
            'issued_at' => $ticket->issued_at?->toIso8601String(),
            'called_at' => $ticket->called_at?->toIso8601String(),
            'started_at' => $ticket->started_at?->toIso8601String(),
            'notes' => $ticket->notes,
        ];
    }

    private function getWaitingTickets(Branch $branch)
    {
        return Ticket::with(['queue:id,name,prefix', 'service:id,name,color'])
            ->where('branch_id', $branch->id)
            ->where('status', TicketStatus::WAITING)
            ->orderByDesc('priority_score')
            ->orderBy('issued_at')
            ->limit(50)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id, 'display_number' => $t->display_number,
                'customer_name' => $t->customer_name,
                'priority' => $t->priority->value, 'priority_label' => $t->priority->label(),
                'queue_name' => $t->queue?->name, 'queue_prefix' => $t->queue?->prefix,
                'service_name' => $t->service?->name, 'service_color' => $t->service?->color,
                'wait_minutes' => $t->issued_at ? max(0, (int) now()->diffInMinutes($t->issued_at)) : 0,
                'issued_at' => $t->issued_at?->toIso8601String(),
            ]);
    }

    private function getQueuesWithCounts(Branch $branch)
    {
        return Queue::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->withCount(['tickets as waiting_count' => fn($q) => $q->where('status', TicketStatus::WAITING)])
            ->get()
            ->map(fn($q) => ['id' => $q->id, 'name' => $q->name, 'prefix' => $q->prefix, 'waiting' => $q->waiting_count]);
    }

    private function getOperatorStats($user): array
    {
        return [
            'served' => Ticket::where('served_by', $user->id)
                ->where('status', TicketStatus::COMPLETED)
                ->whereDate('completed_at', today())
                ->count(),
            'avg_service' => (int) Ticket::where('served_by', $user->id)
                ->where('status', TicketStatus::COMPLETED)
                ->whereDate('completed_at', today())
                ->avg('service_time_seconds'),
            'avg_rating' => Ticket::where('served_by', $user->id)
                ->whereDate('created_at', today())
                ->whereNotNull('rating')
                ->avg('rating'),
        ];
    }
}
