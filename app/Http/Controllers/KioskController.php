<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
// Importación corregida para evitar el error previo
use Illuminate\Support\Facades\Log;

class KioskController extends Controller
{
    /**
     * Vista del kiosco: seleccionar servicio y emitir turno.
     */
    public function index(Request $request, Branch $branch): Response
    {
        // LOG DE INICIO: Si ves esto, la ruta está funcionando bien.
        Log::info('--- ACCESO AL KIOSKO ---', [
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'is_active' => $branch->is_active
        ]);

        /* 
        if (!$branch->is_active) {
            Log::warning('BLOQUEO: Sucursal inactiva intentando acceder al kiosko.');
            abort(404, 'Sucursal no disponible.');
        }
        */

        // Servicios disponibles con su cola asociada
        $queues = Queue::where('branch_id', $branch->id)
            ->active()
            ->with(['services' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->get();

        Log::info('Colas encontradas para esta sucursal:', ['cantidad' => $queues->count()]);

        $services = $queues->flatMap(function ($queue) {
            return $queue->services->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'color' => $s->color,
                'icon' => $s->icon,
                'description' => $s->description,
                'estimated_minutes' => $s->estimated_duration_minutes,
                'queue_id' => $queue->id,
                'queue_name' => $queue->name,
                'queue_prefix' => $queue->prefix,
            ]);
        })->unique('id')->values();

        Log::info('Servicios listos para mostrar:', ['total_services' => $services->count()]);

        // Info en tiempo real
        $waitingCount = Ticket::where('branch_id', $branch->id)
            ->where('status', TicketStatus::WAITING)
            ->whereDate('created_at', today())
            ->count();

        $avgWait = (int) Ticket::where('branch_id', $branch->id)
            ->where('status', TicketStatus::COMPLETED)
            ->whereDate('created_at', today())
            ->whereNotNull('wait_time_seconds')
            ->avg('wait_time_seconds');

        Log::info('Enviando datos a Inertia (Public/Kiosk)');

        return Inertia::render('Public/Kiosk', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                // FUERZA estos valores a true para probar:
                'is_open' => true, 
                'accepts_walkins' => true,
            ],
            'services' => $services,
            'waitingCount' => $waitingCount,
            'avgWaitMinutes' => (int) round($avgWait / 60),
        ]);
    }

    /**
     * Emitir un turno desde el kiosco.
     */
    public function store(Request $request, Branch $branch)
    {
        Log::info('Intentando emitir turno...', $request->all());

        $request->validate([
            'service_id' => 'required|exists:services,id',
            'queue_id' => 'required|exists:queues,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        if (!$branch->is_active || !$branch->accepts_walkins) {
            return back()->withErrors(['branch' => 'Esta sucursal no acepta turnos en este momento.']);
        }

        if (!$branch->isOpen()) {
            return back()->withErrors(['branch' => 'La sucursal está cerrada.']);
        }

        $queue = Queue::findOrFail($request->queue_id);

        if ($queue->isFull()) {
            return back()->withErrors(['queue' => 'La cola está llena. Intente más tarde.']);
        }

        if ($branch->todayTicketCount() >= $branch->max_daily_tickets) {
            return back()->withErrors(['branch' => 'Se alcanzó el límite diario de turnos.']);
        }

        return DB::transaction(function () use ($request, $branch, $queue) {
            $seq = Ticket::where('branch_id', $branch->id)
                ->whereDate('created_at', today())
                ->max('daily_sequence') + 1;

            $ticketNumber = sprintf('%s-%03d', $queue->prefix, $seq);
            $displayNumber = sprintf('%s-%s', $branch->code, $ticketNumber);

            $ticket = Ticket::create([
                'branch_id' => $branch->id,
                'queue_id' => $queue->id,
                'service_id' => $request->service_id,
                'ticket_number' => $ticketNumber,
                'daily_sequence' => $seq,
                'display_number' => $displayNumber,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'status' => TicketStatus::WAITING,
                'priority' => TicketPriority::NORMAL,
                'priority_score' => TicketPriority::NORMAL->weight(),
                'issued_at' => now(),
            ]);

            $ticket->events()->create([
                'event_type' => 'ticket_issued',
                'to_status' => TicketStatus::WAITING->value,
                'occurred_at' => now(),
                'payload' => ['source' => 'kiosk'],
            ]);

            Log::info('Ticket creado con éxito:', ['ticket' => $ticket->display_number]);

            return redirect()->route('kiosk.status', [
                'branch' => $branch->id,
                'ticket' => $ticket->id,
            ]);
        });
    }

    /**
     * Pantalla de confirmación / estado del turno emitido.
     */
    public function status(Request $request, Branch $branch, Ticket $ticket): Response
    {
        $position = $ticket->isActive() ? $ticket->positionInQueue() : null;
        $estimatedWait = $ticket->isActive() ? $ticket->estimatedWaitMinutes() : null;

        return Inertia::render('Public/TicketStatus', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
            ],
            'ticket' => [
                'id' => $ticket->id,
                'display_number' => $ticket->display_number,
                'customer_name' => $ticket->customer_name,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'status_color' => $ticket->status->color(),
                'service_name' => $ticket->service?->name,
                'queue_name' => $ticket->queue?->name,
                'counter_number' => $ticket->counter?->number,
                'position' => $position,
                'estimated_wait_minutes' => $estimatedWait,
                'issued_at' => $ticket->issued_at?->toIso8601String(),
                'called_at' => $ticket->called_at?->toIso8601String(),
            ],
        ]);
    }
}