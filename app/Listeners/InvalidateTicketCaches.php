<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketCalled;
use App\Events\TicketCompleted;
use App\Events\TicketIssued;
use App\Events\TicketTransferred;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates cached metrics whenever a ticket changes state.
 *
 * This ensures dashboards, displays, and analytics always show
 * fresh data within seconds of any ticket action.
 *
 * Register in EventServiceProvider:
 *   TicketIssued::class     => [InvalidateTicketCaches::class],
 *   TicketCalled::class     => [InvalidateTicketCaches::class],
 *   TicketCompleted::class  => [InvalidateTicketCaches::class],
 *   TicketTransferred::class => [InvalidateTicketCaches::class],
 */
class InvalidateTicketCaches
{
    public function handle(object $event): void
    {
        $ticket = $this->extractTicket($event);
        if (! $ticket) {
            return;
        }

        $branchId = $ticket->branch_id;
        $today = today()->toDateString();

        // Invalidate dashboard metrics cache (used by DashboardController::getTodayStats)
        Cache::forget("metrics:today:{$branchId}:{$today}");

        // Invalidate display data cache (used by DisplayController::getDisplayData)
        Cache::forget("display:data:{$branchId}");

        // Invalidate API display cache
        Cache::forget("api:display:{$branchId}");

        // Invalidate branch comparison cache (tenant-level)
        if ($ticket->branch) {
            $tenantId = $ticket->branch->tenant_id ?? null;
            if ($tenantId) {
                Cache::forget("metrics:branches:{$tenantId}:{$today}");
            }
        }
    }

    private function extractTicket(object $event): ?Ticket
    {
        // All ticket events pass the ticket as first constructor arg
        if (property_exists($event, 'ticket')) {
            return $event->ticket;
        }

        // TicketTransferred passes (newTicket, oldTicket)
        if (method_exists($event, '__construct')) {
            $ref = new \ReflectionClass($event);
            $props = $ref->getProperties();
            foreach ($props as $prop) {
                $prop->setAccessible(true);
                $val = $prop->getValue($event);
                if ($val instanceof Ticket) {
                    return $val;
                }
            }
        }

        return null;
    }
}
