<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Deployment and uptime verification.
 *
 * Deliberately unauthenticated so an uptime monitor can reach it, and deliberately
 * content-free: it reports whether the app and database are alive, never anything about
 * the data. A health endpoint that leaks version numbers or row counts is a reconnaissance
 * gift.
 */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        $database = $this->checkDatabase();

        $healthy = $database === true;

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => [
                'database' => $database === true ? 'ok' : 'failed',
            ],
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    /**
     * A real query, not just a connection check — a connection can succeed against a
     * database that is mid-migration and unusable.
     */
    private function checkDatabase(): bool|string
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable $e) {
            // The message is safe to surface: it is a driver-level error, not user data.
            return $e->getMessage();
        }
    }
}
