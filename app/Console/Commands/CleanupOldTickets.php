<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

class CleanupOldTickets extends Command
{
    protected $signature = 'turnos:cleanup-tickets
                            {--days=90 : Delete tickets older than this many days}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Soft-delete tickets and their events older than N days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $query = Ticket::where('created_at', '<', $cutoff)
            ->whereIn('status', ['completed', 'cancelled', 'no_show']);

        $count = $query->count();

        if ($count === 0) {
            $this->info("No hay turnos anteriores a {$cutoff->toDateString()} para limpiar.");
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY RUN] Se eliminarían {$count} turnos anteriores a {$cutoff->toDateString()}.");
            return self::SUCCESS;
        }

        // Soft-delete in chunks to avoid memory issues
        $deleted = 0;
        Ticket::where('created_at', '<', $cutoff)
            ->whereIn('status', ['completed', 'cancelled', 'no_show'])
            ->chunkById(500, function ($tickets) use (&$deleted) {
                foreach ($tickets as $ticket) {
                    $ticket->delete(); // SoftDelete
                    $deleted++;
                }
            });

        $this->info("✓ {$deleted} turnos eliminados (soft-delete) anteriores a {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
