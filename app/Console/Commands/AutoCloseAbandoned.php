<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Console\Command;

class AutoCloseAbandoned extends Command
{
    protected $signature = 'turnos:auto-close
                            {--waiting-minutes=120 : Cancel waiting tickets older than N minutes}
                            {--called-minutes=15 : Mark as no-show called tickets older than N minutes}
                            {--dry-run : Show what would be closed without actually closing}';

    protected $description = 'Auto-close abandoned tickets (waiting too long or called but never showed up)';

    public function handle(): int
    {
        $waitingMinutes = (int) $this->option('waiting-minutes');
        $calledMinutes = (int) $this->option('called-minutes');
        $dryRun = $this->option('dry-run');

        $now = now();
        $closedWaiting = 0;
        $closedCalled = 0;

        // 1. Cancel tickets in WAITING status for too long
        $waitingCutoff = $now->copy()->subMinutes($waitingMinutes);
        $staleWaiting = Ticket::where('status', TicketStatus::WAITING)
            ->where('issued_at', '<', $waitingCutoff)
            ->whereDate('created_at', today());

        $waitingCount = $staleWaiting->count();

        if ($waitingCount > 0) {
            if ($dryRun) {
                $this->warn("[DRY RUN] Cancelaría {$waitingCount} turnos en espera > {$waitingMinutes} min.");
            } else {
                $staleWaiting->each(function (Ticket $ticket) use (&$closedWaiting) {
                    $ticket->update([
                        'status' => TicketStatus::CANCELLED,
                        'cancelled_at' => now(),
                        'notes' => ($ticket->notes ? $ticket->notes . ' | ' : '') . 'Auto-cancelado: tiempo de espera excedido',
                    ]);

                    $ticket->events()->create([
                        'event_type' => 'auto_cancelled',
                        'from_status' => TicketStatus::WAITING->value,
                        'to_status' => TicketStatus::CANCELLED->value,
                        'occurred_at' => now(),
                        'payload' => ['reason' => 'waiting_timeout'],
                    ]);

                    $closedWaiting++;
                });
                $this->info("  ✓ {$closedWaiting} turnos en espera auto-cancelados (> {$waitingMinutes} min).");
            }
        }

        // 2. Mark as NO_SHOW tickets in CALLED status for too long
        $calledCutoff = $now->copy()->subMinutes($calledMinutes);
        $staleCalled = Ticket::where('status', TicketStatus::CALLED)
            ->where('called_at', '<', $calledCutoff)
            ->whereDate('created_at', today());

        $calledCount = $staleCalled->count();

        if ($calledCount > 0) {
            if ($dryRun) {
                $this->warn("[DRY RUN] Marcaría {$calledCount} turnos llamados como no-show > {$calledMinutes} min.");
            } else {
                $staleCalled->each(function (Ticket $ticket) use (&$closedCalled) {
                    $ticket->update([
                        'status' => TicketStatus::NO_SHOW,
                        'cancelled_at' => now(),
                        'notes' => ($ticket->notes ? $ticket->notes . ' | ' : '') . 'Auto no-show: no se presentó a ventanilla',
                    ]);

                    // Free the counter if assigned
                    if ($ticket->counter_id) {
                        \App\Models\Counter::where('id', $ticket->counter_id)->update([
                            'current_ticket_id' => null,
                            'status' => 'open',
                        ]);
                    }

                    $ticket->events()->create([
                        'event_type' => 'auto_no_show',
                        'from_status' => TicketStatus::CALLED->value,
                        'to_status' => TicketStatus::NO_SHOW->value,
                        'occurred_at' => now(),
                        'payload' => ['reason' => 'called_timeout'],
                    ]);

                    $closedCalled++;
                });
                $this->info("  ✓ {$closedCalled} turnos llamados marcados como no-show (> {$calledMinutes} min).");
            }
        }

        $total = $closedWaiting + $closedCalled;
        if ($total === 0 && !$dryRun) {
            $this->info('No hay turnos abandonados para cerrar.');
        }

        return self::SUCCESS;
    }
}
