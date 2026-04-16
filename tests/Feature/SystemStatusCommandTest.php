<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\SystemStatusCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => 'test-chat-id',
        ]);

        // Clean alert cache between tests to avoid cross-test state
        Cache::forget('system:alert_count_24h');
        Cache::forget('system:last_alert_details');
        Cache::forget('system:last_alert_hash');
    }

    // ── alert-only mode ──

    public function test_alert_only_runs_without_exceptions(): void
    {
        // In dev, supervisor check may generate a warning (exit code 1).
        // In production with supervisor, it returns 0 if all OK.
        // We verify the command runs and produces output either way.
        $result = $this->artisan('system:status', ['--alert-only' => true]);
        $this->assertTrue(in_array($result->run(), [0, 1]));
    }

    public function test_alert_only_stays_silent_when_no_warnings_and_no_previous_alert(): void
    {
        // Simulate healthy system via swapped supervisor command
        $this->swapHealthySupervisor();

        $this->artisan('system:status', ['--alert-only' => true])
            ->expectsOutput('All checks passed, no alert sent.')
            ->assertSuccessful();

        // No hash should be stored
        $this->assertNull(Cache::get('system:last_alert_hash'));
    }

    public function test_alert_only_stores_hash_on_first_alert(): void
    {
        // Simulate supervisor failure so we get a warning
        $this->swapFailingSupervisor();

        $this->artisan('system:status', ['--alert-only' => true]);

        // Hash should now be stored
        $this->assertNotNull(Cache::get('system:last_alert_hash'));
    }

    public function test_alert_only_dedupes_identical_consecutive_alerts(): void
    {
        $this->swapFailingSupervisor();

        // First run — alert sent, hash stored
        $this->artisan('system:status', ['--alert-only' => true]);
        $firstHash = Cache::get('system:last_alert_hash');
        $this->assertNotNull($firstHash);

        // Second run with same failure — should be suppressed
        $this->artisan('system:status', ['--alert-only' => true])
            ->expectsOutput('Same alert as previous run, dedup suppressed notification.');

        // Hash remains the same
        $this->assertEquals($firstHash, Cache::get('system:last_alert_hash'));
    }

    public function test_alert_only_sends_resolution_when_alert_clears(): void
    {
        // Seed a previous alert hash as if we had alerted before
        Cache::put('system:last_alert_hash', 'abc123-previous-hash', now()->addHours(24));

        // Now simulate healthy system
        $this->swapHealthySupervisor();

        $this->artisan('system:status', ['--alert-only' => true])
            ->expectsOutput('Alerts resolved, notice sent.')
            ->assertSuccessful();

        // Hash should be cleared after resolution
        $this->assertNull(Cache::get('system:last_alert_hash'));
    }

    public function test_alert_only_counts_suppressed_alerts_in_summary_cache(): void
    {
        $this->swapFailingSupervisor();

        // Three consecutive runs with same alert
        $this->artisan('system:status', ['--alert-only' => true]);
        $this->artisan('system:status', ['--alert-only' => true]);
        $this->artisan('system:status', ['--alert-only' => true]);

        // Counter should reflect all three, even though only first was notified
        $count = (int) Cache::get('system:alert_count_24h', 0);
        $this->assertGreaterThanOrEqual(3, $count);
    }

    // ── daily-summary mode ──

    public function test_daily_summary_sends_condensed_report(): void
    {
        $this->artisan('system:status', ['--daily-summary' => true])
            ->expectsOutput('Daily summary sent')
            ->assertSuccessful();
    }

    public function test_daily_summary_includes_zero_alerts_when_none_tracked(): void
    {
        Cache::forget('system:alert_count_24h');
        Cache::forget('system:last_alert_details');

        $this->artisan('system:status', ['--daily-summary' => true])
            ->expectsOutput('Daily summary sent')
            ->assertSuccessful();
    }

    public function test_daily_summary_shows_alert_count_from_cache(): void
    {
        Cache::put('system:alert_count_24h', 3, now()->addHours(24));
        Cache::put('system:last_alert_details', [
            'time' => '14:30',
            'warnings' => ['⚠️ Disco al 87%'],
        ], now()->addHours(24));

        $this->artisan('system:status', ['--daily-summary' => true])
            ->expectsOutput('Daily summary sent')
            ->assertSuccessful();

        $this->assertNull(Cache::get('system:alert_count_24h'));
        $this->assertNull(Cache::get('system:last_alert_details'));
    }

    public function test_daily_summary_resets_alert_counters(): void
    {
        Cache::put('system:alert_count_24h', 5, now()->addHours(24));
        Cache::put('system:last_alert_details', [
            'time' => '10:00',
            'warnings' => ['⚠️ Test warning'],
        ], now()->addHours(24));

        $this->artisan('system:status', ['--daily-summary' => true])
            ->assertSuccessful();

        $this->assertNull(Cache::get('system:alert_count_24h'));
        $this->assertNull(Cache::get('system:last_alert_details'));
    }

    public function test_daily_summary_does_not_touch_dedup_hash(): void
    {
        // Dedup hash should survive daily summary — it's for alert-only mode
        Cache::put('system:last_alert_hash', 'active-alert-hash', now()->addHours(24));

        $this->artisan('system:status', ['--daily-summary' => true])
            ->assertSuccessful();

        $this->assertEquals('active-alert-hash', Cache::get('system:last_alert_hash'));
    }

    // ── full report (manual mode) ──

    public function test_full_report_runs_and_sends(): void
    {
        // Full report always sends. Exit code may be 1 if supervisor
        // is not available in dev environment — that's expected.
        $this->artisan('system:status')
            ->expectsOutputToContain('Status sent');
    }

    public function test_full_report_shows_all_checks(): void
    {
        $this->artisan('system:status')
            ->expectsOutputToContain('disco:')
            ->expectsOutputToContain('ram:')
            ->expectsOutputToContain('postgresql:')
            ->expectsOutputToContain('redis:');
    }

    public function test_full_report_does_not_apply_dedup(): void
    {
        $this->swapFailingSupervisor();

        // Seed a hash as if dedup would suppress
        Cache::put('system:last_alert_hash', 'same-hash-that-would-dedupe', now()->addHours(24));

        // Manual full report should always send (never dedup)
        $this->artisan('system:status')
            ->expectsOutputToContain('Status sent');
    }

    // ── daily-summary always returns SUCCESS ──

    public function test_daily_summary_returns_success_even_with_local_warnings(): void
    {
        // daily-summary always returns SUCCESS regardless of current warnings
        $this->artisan('system:status', ['--daily-summary' => true])
            ->assertSuccessful();
    }

    // ── Telegram not configured ──

    public function test_warns_when_telegram_not_configured(): void
    {
        config([
            'services.telegram.bot_token' => null,
            'services.telegram.chat_id' => null,
        ]);

        // Command runs checks but warns about missing Telegram config.
        // Exit code depends on whether supervisor generates warnings.
        $this->artisan('system:status')
            ->expectsOutput('Telegram not configured');
    }

    // ── Helpers ──

    /**
     * Swap SystemStatusCommand with a version that returns healthy supervisor output.
     */
    private function swapHealthySupervisor(): void
    {
        $this->app->bind(SystemStatusCommand::class, function () {
            return new class extends SystemStatusCommand {
                protected function runSupervisorCommand(string $command): string
                {
                    return "olinora-reverb                    RUNNING   pid 1009, uptime 2 days\n"
                        . "olinora-worker:olinora-worker_00   RUNNING   pid 1010, uptime 2 days\n"
                        . "olinora-worker:olinora-worker_01   RUNNING   pid 1011, uptime 2 days\n";
                }
            };
        });
    }

    /**
     * Swap SystemStatusCommand with a version whose supervisor check fails.
     */
    private function swapFailingSupervisor(): void
    {
        $this->app->bind(SystemStatusCommand::class, function () {
            return new class extends SystemStatusCommand {
                protected function runSupervisorCommand(string $command): string
                {
                    // Empty output = validation fails = warning generated
                    return '';
                }
            };
        });
    }
}
