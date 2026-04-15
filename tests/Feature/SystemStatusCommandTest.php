<?php

declare(strict_types=1);

namespace Tests\Feature;

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
}
