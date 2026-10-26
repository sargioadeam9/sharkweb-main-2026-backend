<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Triggers Next.js on-demand ISR revalidation (§6).
 *
 * POSTs to {NEXTJS_URL}/api/revalidate with the shared secret in a header, so a cache
 * purge can never be triggered by an anonymous caller.
 *
 * Two invariants, both deliberate:
 *
 *  1. **This never throws.** It runs from model observers, i.e. inside an admin's save
 *     request. A frontend that is down, slow, or misconfigured must not prevent the
 *     editor from saving their work. Every failure is caught, logged, and recorded.
 *
 *  2. **It never queues.** The production target is shared cPanel hosting with no
 *     queue worker (docs/BACKEND_MVP_PLAN.md §4), so a queued job would never run.
 *     The call is synchronous with a short timeout and a small bounded retry.
 */
class IsrWebhookService
{
    /**
     * Ask the frontend to rebuild the given paths and/or cache tags.
     *
     * @param  list<string>  $paths  Relative paths, e.g. ['/blog', '/blog/my-post']
     * @param  list<string>  $tags  Next.js cache tags, e.g. ['posts', 'post:my-post']
     * @return bool True when the frontend acknowledged the purge.
     */
    public function revalidate(array $paths = [], array $tags = [], string $event = 'content.updated'): bool
    {
        $paths = array_values(array_unique(array_filter($paths)));
        $tags = array_values(array_unique(array_filter($tags)));

        if ($paths === [] && $tags === []) {
            return false;
        }

        if (! config('sharkweb.isr.enabled')) {
            Log::debug('ISR revalidation skipped (disabled).', compact('paths', 'tags', 'event'));

            return false;
        }

        $baseUrl = rtrim((string) config('sharkweb.isr.nextjs_url'), '/');
        $secret = (string) config('sharkweb.isr.secret');

        if ($baseUrl === '' || $secret === '') {
            // Misconfiguration, not a transient error — worth a warning so it gets fixed.
            Log::warning('ISR revalidation skipped: NEXTJS_URL or REVALIDATE_SECRET is not configured.');

            return false;
        }

        $url = $baseUrl.config('sharkweb.isr.endpoint', '/api/revalidate');
        $payload = ['paths' => $paths, 'tags' => $tags, 'event' => $event];

        $startedAt = microtime(true);
        $status = null;
        $body = null;
        $attempts = 0;

        try {
            $attempts = max(1, (int) config('sharkweb.isr.retries', 2));
            $backoff = (int) config('sharkweb.isr.retry_backoff_ms', 300);

            $response = Http::timeout((int) config('sharkweb.isr.timeout', 5))
                ->acceptJson()
                ->asJson()
                ->withHeaders(['X-Revalidate-Secret' => $secret])
                // throw: false keeps a 4xx/5xx from raising; we inspect the status instead.
                ->retry($attempts, $backoff, throw: false)
                ->post($url, $payload);

            $status = $response->status();
            $body = $response->body();
        } catch (Throwable $e) {
            // Connection refused, DNS failure, TLS error, timeout.
            $body = $e->getMessage();
            Log::warning('ISR revalidation transport failure.', ['url' => $url, 'error' => $body]);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->record($event, $payload, $status, $body, $durationMs, $attempts);

        return $status !== null && $status >= 200 && $status < 300;
    }

    /**
     * Convenience wrapper for a single record: purges its paths and its tag.
     *
     * @param  list<string>  $paths
     */
    public function revalidatePaths(array $paths, string $event, ?string $tag = null): bool
    {
        return $this->revalidate($paths, $tag ? [$tag] : [], $event);
    }

    /**
     * Persist the attempt. Guarded because logging must never be the thing that breaks
     * a save — e.g. during a migration the table may not exist yet.
     */
    private function record(
        string $event,
        array $payload,
        ?int $status,
        ?string $body,
        int $durationMs,
        int $attempts,
    ): void {
        try {
            WebhookLog::record($event, $payload, $status, $body, $durationMs, $attempts);
        } catch (Throwable $e) {
            Log::warning('Could not write webhook log.', ['error' => $e->getMessage()]);
        }
    }
}
