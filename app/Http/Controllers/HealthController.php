<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * Public health check endpoint for CI/CD post-deploy verification.
     *
     * Returns HTTP 200 if all critical services are reachable,
     * HTTP 503 if any critical service is down.
     *
     * No authentication required — this is called by GitHub Actions
     * immediately after deploy to verify the app is alive.
     *
     * Intentionally minimal: no sensitive data, no metrics, no PII.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $allOk = true;

        // ── Database ──
        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'down';
            $allOk = false;
        }

        // ── Redis ──
        try {
            Redis::ping();
            $checks['redis'] = 'ok';
        } catch (\Throwable) {
            $checks['redis'] = 'down';
            $allOk = false;
        }

        // ── Supervisor (best-effort, non-critical for HTTP) ──
        $supervisorOutput = @shell_exec('/usr/bin/supervisorctl status 2>&1') ?? '';
        $runningCount = substr_count($supervisorOutput, 'RUNNING');
        $checks['supervisor'] = $runningCount >= 3 ? 'ok' : 'degraded';

        // Supervisor degraded doesn't make the whole app "down" —
        // queue workers restart automatically. Only DB/Redis are critical.

        $status = $allOk ? 'ok' : 'down';
        $httpCode = $allOk ? 200 : 503;

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $httpCode);
    }
}
