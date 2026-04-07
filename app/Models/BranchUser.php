<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for branch_user table.
 * Uses ULIDs as primary key (matches migration definition).
 */
class BranchUser extends Pivot
{
    use HasUlids;

    protected $table = 'branch_user';

    public $incrementing = false;

    protected $keyType = 'string';
}
