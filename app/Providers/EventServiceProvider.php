<?php

namespace App\Providers;

use App\Events\TicketCalled;
use App\Events\TicketCompleted;
use App\Events\TicketIssued;
use App\Events\TicketTransferred;
use App\Listeners\InvalidateTicketCaches;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // ── Ticket lifecycle events ──
        // Each event invalidates cached metrics so dashboards stay fresh,
        // and broadcasts via Reverb so displays/operators update in real-time.
        TicketIssued::class => [
            InvalidateTicketCaches::class,
        ],
        TicketCalled::class => [
            InvalidateTicketCaches::class,
        ],
        TicketCompleted::class => [
            InvalidateTicketCaches::class,
        ],
        TicketTransferred::class => [
            InvalidateTicketCaches::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
