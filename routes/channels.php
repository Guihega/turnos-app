<?php

use App\Models\Branch;
use Illuminate\Support\Facades\Broadcast;

// User private channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Branch channel — operators and managers can listen
Broadcast::channel('branch.{branchId}', function ($user, $branchId) {
    if ($user->isSuperAdmin() || $user->isTenantAdmin()) {
        $branch = Branch::find($branchId);
        return $branch && $branch->tenant_id === $user->tenant_id;
    }

    return $user->belongsToBranch($branchId);
});

// Public branch channel (display screens — no auth required)
Broadcast::channel('display.{branchId}', function () {
    return true; // Public channel for TV displays
});
