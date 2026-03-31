<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user, string $branchId): bool
    {
        return $user->hasPermission('tickets.view')
            && $user->belongsToBranch($branchId);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.view')
            && $user->belongsToBranch($ticket->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tickets.create');
    }

    public function call(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.call')
            && $user->belongsToBranch($ticket->branch_id)
            && $ticket->status === TicketStatus::WAITING;
    }

    public function serve(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.serve')
            && $ticket->served_by === $user->id
            && $ticket->status === TicketStatus::CALLED;
    }

    public function complete(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.complete')
            && $ticket->served_by === $user->id
            && $ticket->status === TicketStatus::IN_PROGRESS;
    }

    public function transfer(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.transfer')
            && $user->belongsToBranch($ticket->branch_id)
            && $ticket->status->isActive();
    }

    public function cancel(User $user, Ticket $ticket): bool
    {
        if ($user->hasPermission('tickets.*')) {
            return $user->belongsToBranch($ticket->branch_id);
        }

        return $user->hasPermission('tickets.serve')
            && $ticket->served_by === $user->id
            && $ticket->status->isActive();
    }
}
