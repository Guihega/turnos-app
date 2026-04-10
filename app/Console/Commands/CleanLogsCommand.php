<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CleanLogsCommand extends Command
{
    protected $signature = 'logs:clean {--days=7 : Delete logs older than this many days}';
    protected $description = 'Clean old log files to free disk space';

    public function handle(): int
    {
        $days = $this->option('days');
        $cutoff = Carbon::now()->subDays($days);
        $totalFreed = 0;
        $filesDeleted = 0;

        $logPaths = [
            storage_path('logs'),
        ];

        foreach ($logPaths as $path) {
            if (!is_dir($path)) continue;

            $files = File::glob("{$path}/*.log");
            foreach ($files as $file) {
                // Never delete the current laravel.log
                if (basename($file) === 'laravel.log') continue;

                $modified = Carbon::createFromTimestamp(filemtime($file));
                if ($modified->lt($cutoff)) {
                    $size = filesize($file);
                    File::delete($file);
                    $totalFreed += $size;
                    $filesDeleted++;
                    $this->line("  Deleted: " . basename($file) . " (" . $this->formatBytes($size) . ")");
                }
            }
        }

        // Truncate current laravel.log if > 50MB
        $currentLog = storage_path('logs/laravel.log');
        if (file_exists($currentLog) && filesize($currentLog) > 50 * 1024 * 1024) {
            $size = filesize($currentLog);
            file_put_contents($currentLog, '');
            $totalFreed += $size;
            $this->line("  Truncated: laravel.log (" . $this->formatBytes($size) . ")");
        }

        // Clean old scheduler output logs
        $schedulerLogs = [
            storage_path('logs/auto-close.log'),
            storage_path('logs/daily-metrics.log'),
            storage_path('logs/cleanup.log'),
            storage_path('logs/health-check.log'),
            storage_path('logs/pilot-reset.log'),
        ];

        foreach ($schedulerLogs as $logFile) {
            if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
                $size = filesize($logFile);
                file_put_contents($logFile, '');
                $totalFreed += $size;
                $this->line("  Truncated: " . basename($logFile) . " (" . $this->formatBytes($size) . ")");
            }
        }

        $this->info("Cleaned {$filesDeleted} files, freed " . $this->formatBytes($totalFreed));

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . 'GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . 'MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . 'KB';
        return $bytes . 'B';
    }
}
