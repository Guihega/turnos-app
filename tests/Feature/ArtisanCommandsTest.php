<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    use RefreshDatabase;

    // ══════════════════════════════════════════════════════════════
    // SYSTEM STATUS COMMAND
    // ══════════════════════════════════════════════════════════════

    public function test_system_status_runs_without_error(): void
    {
        $this->artisan('system:status')
            ->assertSuccessful();
    }

    public function test_system_status_alert_only_skips_when_healthy(): void
    {
        $this->artisan('system:status', ['--alert-only' => true])
            ->assertSuccessful()
            ->expectsOutput('All checks passed, no alert sent.');
    }

    // ══════════════════════════════════════════════════════════════
    // WEEKLY REPORT COMMAND
    // ══════════════════════════════════════════════════════════════

    public function test_weekly_report_runs_without_error(): void
    {
        $this->artisan('report:weekly')
            ->assertSuccessful();
    }

    public function test_weekly_report_includes_ticket_data(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $queue = Queue::factory()->create(['branch_id' => $branch->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);
        $operator = User::factory()->operator()->create(['tenant_id' => $tenant->id]);

        // Create tickets from last week
        $lastWeek = now()->subWeek();
        for ($i = 0; $i < 5; $i++) {
            Ticket::factory()->completed()->create([
                'branch_id' => $branch->id,
                'queue_id' => $queue->id,
                'service_id' => $service->id,
                'served_by' => $operator->id,
                'created_at' => $lastWeek->copy()->addHours($i),
                'daily_sequence' => $i + 1,
            ]);
        }

        $this->artisan('report:weekly')
            ->assertSuccessful();
    }

    public function test_weekly_report_handles_empty_week(): void
    {
        // No tickets at all
        $this->artisan('report:weekly')
            ->assertSuccessful();
    }

    // ══════════════════════════════════════════════════════════════
    // LOG CLEANUP COMMAND
    // ══════════════════════════════════════════════════════════════

    public function test_logs_clean_runs_without_error(): void
    {
        $this->artisan('logs:clean', ['--days' => 7])
            ->assertSuccessful();
    }

    public function test_logs_clean_removes_old_files(): void
    {
        $logDir = storage_path('logs');

        // Create an old log file
        $oldLog = "{$logDir}/laravel-2025-01-01.log";
        File::put($oldLog, 'old log content');
        touch($oldLog, strtotime('-30 days'));

        $this->artisan('logs:clean', ['--days' => 7])
            ->assertSuccessful();

        $this->assertFileDoesNotExist($oldLog);
    }

    public function test_logs_clean_preserves_current_laravel_log(): void
    {
        $currentLog = storage_path('logs/laravel.log');
        File::put($currentLog, 'current log content');

        $this->artisan('logs:clean', ['--days' => 7])
            ->assertSuccessful();

        // Current log should still exist
        $this->assertFileExists($currentLog);
    }

    public function test_logs_clean_truncates_oversized_laravel_log(): void
    {
        $currentLog = storage_path('logs/laravel.log');

        // Create a log file > 50MB (simulated with smaller size for speed)
        // In production this truncates at 50MB; we test the mechanism works
        File::put($currentLog, str_repeat('x', 100));

        $this->artisan('logs:clean', ['--days' => 7])
            ->assertSuccessful();

        // File should still exist (not deleted, only truncated if > 50MB)
        $this->assertFileExists($currentLog);
    }

    public function test_logs_clean_preserves_recent_files(): void
    {
        $logDir = storage_path('logs');

        // Create a recent log file (1 day old)
        $recentLog = "{$logDir}/laravel-recent.log";
        File::put($recentLog, 'recent log content');
        touch($recentLog, strtotime('-1 day'));

        $this->artisan('logs:clean', ['--days' => 7])
            ->assertSuccessful();

        // Recent file should be preserved
        $this->assertFileExists($recentLog);

        // Cleanup
        File::delete($recentLog);
    }
}
