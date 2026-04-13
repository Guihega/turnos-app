<?php

use App\Models\Branch;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Channel authorization for WebSocket connections.
| Private channels require authentication.
| Presence channels also share who is subscribed.
|
*/

// ── User personal channel (notifications) ──
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

// ── Branch channel (operators, managers — ticket events) ──
// Used for: TicketCalled, TicketCompleted, TicketIssued, TicketTransferred
Broadcast::channel('branch.{branchId}', function ($user, $branchId) {
    return $user->belongsToBranch($branchId);
});

// ── Display channel (authenticated waiting room screens) ──
Broadcast::channel('display.{branchId}', function ($user, $branchId) {
    return $user->belongsToBranch($branchId);
});

// ── Operator presence channel (who is online in a branch) ──
Broadcast::channel('operators.{branchId}', function ($user, $branchId) {
    if ($user->belongsToBranch($branchId)) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role->value,
        ];
    }
    return false;
});
