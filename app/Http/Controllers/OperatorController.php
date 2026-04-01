<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketCalled;
use App\Events\TicketCompleted;
use App\Events\TicketTransferred;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OperatorController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Obtener sucursales según rol
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

        Log::info('OperatorController::index', [
            'user' => $user->name,
            'role' => $user->role->value ?? $user->role,
            'branch' => $branch->name,
            'branch_id' => $branch->id,
        ]);

        // ── Ventanilla del operador ──
        $counter = Counter::where('branch_id', $branch->id)
            ->where('current_operator_id', $user->id)
            ->first();

        // Ventanillas disponibles (sin operador asignado + la propia)
        $availableCounters = Counter::where('branch_id', $branch->id)
            ->where(fn($q) => $q->whereNull('current_operator_id')->orWhere('current_operator_id', $user->id))
            ->orderBy('number')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'number' => $c->number,
                'status' => $c->status,
                'is_mine' => $c->current_operator_id === $user->id,
            ]);

        // ── Turno actual del operador (llamado o en atención) ──
        $currentTicket = Ticket::with(['queue:id,name,prefix', 'service:id,name,color', 'counter:id,name,number'])
            ->where('served_by', $user->id)
            ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_PROGRESS])
            ->first();

        $currentTicketData = null;
        if ($currentTicket) {
            $currentTicketData = [
                'id' => $currentTicket->id,
                'display_number' => $currentTicket->display_number,
                'customer_name' => $currentTicket->customer_name,
                'customer_phone' => $currentTicket->customer_phone,
                'status' => $currentTicket->status->value,
                'status_label' => $currentTicket->status->label(),
                'priority' => $currentTicket->priority->value,
                'priority_label' => $currentTicket->priority->label(),
                'queue_id' => $currentTicket->queue_id,
                'queue_name' => $currentTicket->queue?->name,
                'queue_prefix' => $currentTicket->queue?->prefix,
                'service_name' => $currentTicket->service?->name,
                'service_color' => $currentTicket->service?->color,
                'counter_number' => $currentTicket->counter?->number,
                'issued_at' => $currentTicket->issued_at?->toIso8601String(),
                'called_at' => $currentTicket->called_at?->toIso8601String(),
                'started_at' => $currentTicket->started_at?->toIso8601String(),
                'notes' => $currentTicket->notes,
            ];
        }

        // ── Cola de espera: TODOS los tickets waiting de esta sucursal ──
        // NO filtra por fecha — muestra todos los que están en status waiting
        $waitingTickets = Ticket::with(['queue:id,name,prefix', 'service:id,name,color'])
            ->where('branch_id', $branch->id)
            ->where('status', TicketStatus::WAITING)
            ->orderByDesc('priority_score')
            ->orderBy('issued_at')
            ->limit(50)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'display_number' => $t->display_number,
                'customer_name' => $t->customer_name,
                'priority' => $t->priority->value,
                'priority_label' => $t->priority->label(),
                'queue_name' => $t->queue?->name,
                'queue_prefix' => $t->queue?->prefix,
                'service_name' => $t->service?->name,
                'service_color' => $t->service?->color,
                'wait_minutes' => $t->issued_at ? max(0, (int) now()->diffInMinutes($t->issued_at)) : 0,
                'issued_at' => $t->issued_at?->toIso8601String(),
            ]);

        Log::info('Waiting tickets found', [
            'branch_id' => $branch->id,
            'count' => $waitingTickets->count(),
            'tickets' => $waitingTickets->pluck('display_number')->toArray(),
        ]);

        // ── Colas con conteo de espera (sin filtro de fecha) ──
        $queues = Queue::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->withCount([
                'tickets as waiting_count' => fn($q) => $q->where('status', TicketStatus::WAITING),
            ])
            ->get()
            ->map(fn($q) => [
                'id' => $q->id,
                'name' => $q->name,
                'prefix' => $q->prefix,
                'waiting' => $q->waiting_count,
            ]);

        // Todas las colas para el selector de transferencia
        $allQueues = Queue::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->get()
            ->map(fn($q) => ['id' => $q->id, 'name' => $q->name, 'prefix' => $q->prefix]);

        // ── Stats personales del operador hoy ──
        $myStats = [
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

        return Inertia::render('Operator/Index', [
            'branches' => $branches->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'code' => $b->code]),
            'currentBranch' => ['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code],
            'counter' => $counter ? ['id' => $counter->id, 'name' => $counter->name, 'number' => $counter->number] : null,
            'availableCounters' => $availableCounters,
            'currentTicket' => $currentTicketData,
            'waitingTickets' => $waitingTickets,
            'queues' => $queues,
            'allQueues' => $allQueues,
            'myStats' => $myStats,
        ]);
    }

    public function callNext(Request $request)
    {
        $request->validate([
            'counter_id' => 'required|exists:counters,id',
            'queue_id' => 'nullable|exists:queues,id',
        ]);

        $user = $request->user();

        // Verificar que no tiene turno activo
        $active = Ticket::where('served_by', $user->id)
            ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_PROGRESS])
            ->first();

        if ($active) {
            return back()->withErrors(['ticket' => "Ya tiene un turno activo: {$active->display_number}"]);
        }

        $counter = Counter::findOrFail($request->counter_id);

        return DB::transaction(function () use ($request, $user, $counter) {
            // Asignar operador al counter
            $counter->update([
                'current_operator_id' => $user->id,
                'status' => 'open',
            ]);

            // Buscar siguiente ticket — SIN filtro de fecha
            $query = Ticket::where('branch_id', $counter->branch_id)
                ->where('status', TicketStatus::WAITING);

            if ($request->queue_id) {
                $query->where('queue_id', $request->queue_id);
            }

            $ticket = $query->orderByDesc('priority_score')
                ->orderBy('issued_at')
                ->lockForUpdate()
                ->first();

            if (!$ticket) {
                return back()->with('info', 'No hay turnos en espera.');
            }

            // Transicionar status
            $ticket->transitionTo(TicketStatus::CALLED, $user->id);
            $ticket->update([
                'served_by' => $user->id,
                'counter_id' => $counter->id,
            ]);

            $counter->update([
                'current_ticket_id' => $ticket->id,
                'status' => 'serving',
            ]);

            Log::info('Ticket called', [
                'ticket' => $ticket->display_number,
                'operator' => $user->name,
                'counter' => $counter->number,
            ]);

            // Dispatch broadcast event
            try {
                TicketCalled::dispatch($ticket->fresh(['queue', 'service', 'counter', 'servedBy']));
            } catch (\Throwable $e) {
                Log::warning('TicketCalled dispatch failed', ['error' => $e->getMessage()]);
            }

            return back()->with('success', "Turno {$ticket->display_number} llamado en ventanilla {$counter->number}.");
        });
    }

    public function startServing(Request $request, Ticket $ticket)
    {
        $user = $request->user();

        if ($ticket->served_by !== $user->id) {
            return back()->withErrors(['ticket' => 'Este turno no está asignado a usted.']);
        }

        if ($ticket->status !== TicketStatus::CALLED) {
            return back()->withErrors(['ticket' => 'El turno no está en estado llamado.']);
        }

        $ticket->transitionTo(TicketStatus::IN_PROGRESS, $user->id);

        return back()->with('success', "Atención iniciada para {$ticket->display_number}.");
    }

    public function complete(Request $request, Ticket $ticket)
    {
        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if ($ticket->served_by !== $user->id) {
            return back()->withErrors(['ticket' => 'Este turno no está asignado a usted.']);
        }

        return DB::transaction(function () use ($request, $ticket, $user) {
            $ticket->transitionTo(TicketStatus::COMPLETED, $user->id);

            if ($request->filled('rating')) {
                $ticket->update(['rating' => $request->rating]);
            }
            if ($request->filled('notes')) {
                $ticket->update(['notes' => $request->notes]);
            }

            // Liberar counter
            Counter::where('id', $ticket->counter_id)->update([
                'current_ticket_id' => null,
                'status' => 'open',
            ]);

            try {
                TicketCompleted::dispatch($ticket->fresh(['queue', 'service', 'servedBy', 'branch']));
            } catch (\Throwable $e) {
                Log::warning('TicketCompleted dispatch failed', ['error' => $e->getMessage()]);
            }

            return back()->with('success', "Turno {$ticket->display_number} completado.");
        });
    }

    public function cancel(Request $request, Ticket $ticket)
    {
        return DB::transaction(function () use ($ticket, $request) {
            $ticket->transitionTo(TicketStatus::CANCELLED, $request->user()->id);

            if ($ticket->counter_id) {
                Counter::where('id', $ticket->counter_id)->update([
                    'current_ticket_id' => null,
                    'status' => 'open',
                ]);
            }

            return back()->with('success', "Turno {$ticket->display_number} cancelado.");
        });
    }

    public function noShow(Request $request, Ticket $ticket)
    {
        return DB::transaction(function () use ($ticket, $request) {
            $ticket->transitionTo(TicketStatus::NO_SHOW, $request->user()->id);

            if ($ticket->counter_id) {
                Counter::where('id', $ticket->counter_id)->update([
                    'current_ticket_id' => null,
                    'status' => 'open',
                ]);
            }

            return back()->with('success', "Turno {$ticket->display_number} marcado como no presentado.");
        });
    }

    public function transfer(Request $request, Ticket $ticket)
    {
        $request->validate([
            'target_queue_id' => 'required|exists:queues,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($request, $ticket, $user) {
            $oldNumber = $ticket->display_number;
            $ticket->transitionTo(TicketStatus::TRANSFERRED, $user->id);

            if ($ticket->counter_id) {
                Counter::where('id', $ticket->counter_id)->update([
                    'current_ticket_id' => null,
                    'status' => 'open',
                ]);
            }

            $targetQueue = Queue::findOrFail($request->target_queue_id);
            $branch = Branch::findOrFail($ticket->branch_id);
            $seq = Ticket::where('branch_id', $branch->id)->whereDate('created_at', today())->max('daily_sequence') + 1;
            $ticketNumber = sprintf('%s-%03d', $targetQueue->prefix, $seq);

            $newTicket = Ticket::create([
                'branch_id' => $ticket->branch_id,
                'queue_id' => $targetQueue->id,
                'service_id' => $ticket->service_id,
                'ticket_number' => $ticketNumber,
                'daily_sequence' => $seq,
                'display_number' => sprintf('%s-%s', $branch->code, $ticketNumber),
                'customer_name' => $ticket->customer_name,
                'customer_phone' => $ticket->customer_phone,
                'customer_email' => $ticket->customer_email,
                'status' => TicketStatus::WAITING,
                'priority' => TicketPriority::HIGH,
                'priority_score' => TicketPriority::HIGH->weight(),
                'issued_at' => now(),
                'transferred_from_id' => $ticket->id,
                'transfer_count' => $ticket->transfer_count + 1,
                'notes' => $request->reason,
            ]);

            try {
                TicketTransferred::dispatch($newTicket, $ticket);
            } catch (\Throwable $e) {
                Log::warning('TicketTransferred dispatch failed', ['error' => $e->getMessage()]);
            }

            return back()->with('success', "Turno {$oldNumber} transferido → {$newTicket->display_number}");
        });
    }

    public function recall(Request $request, Ticket $ticket)
    {
        if ($ticket->status !== TicketStatus::CALLED) {
            return back()->withErrors(['ticket' => 'Solo se pueden rellamar turnos en estado "llamado".']);
        }

        try {
            TicketCalled::dispatch($ticket->load(['queue', 'service', 'counter', 'servedBy']));
        } catch (\Throwable $e) {
            Log::warning('TicketCalled recall dispatch failed', ['error' => $e->getMessage()]);
        }

        return back()->with('success', "Turno {$ticket->display_number} rellamado.");
    }
}
