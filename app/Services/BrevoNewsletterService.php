<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NewsletterSubscriber;
use App\Models\WebhookLog;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Newsletter sync with Brevo (§Module G).
 *
 * Brevo (formerly Sendinblue) is used over the Resend audience API because it is a proper
 * marketing platform: it handles double opt-in, unsubscribe compliance and list hygiene,
 * which are legal requirements for bulk email and not something to hand-roll.
 *
 * The subscriber is persisted BEFORE the remote call. If Brevo is down the local row still
 * exists with `brevo_synced = false`, and `NewsletterSubscriber::pendingSync()` can retry
 * it later. Losing a sign-up because a third party had an outage is not acceptable.
 *
 * Never throws, never queues — same contract as the other outbound services here.
 */
class BrevoNewsletterService
{
    /**
     * The credential in use: the panel's, then `.env`.
     *
     * The panel wins so the owner can rotate a revoked key without touching the server, and
     * `.env` stays as the fallback for a fresh install. Same rule as the Resend key.
     */
    public function key(): string
    {
        $key = trim((string) app(GeneralSettings::class)->brevo_api_key);

        return $key !== '' ? $key : trim((string) config('sharkweb.brevo.api_key'));
    }

    public function listId(): int
    {
        $id = trim((string) app(GeneralSettings::class)->brevo_list_id);

        if ($id === '') {
            $id = trim((string) config('sharkweb.brevo.list_id'));
        }

        return (int) $id;
    }

    /**
     * Whether there is enough configuration to attempt a sync at all.
     *
     * Callers use this to say "not configured" instead of "Brevo rejected the contact", which
     * is what the panel used to claim while nothing had been contacted.
     */
    public function configured(): bool
    {
        return $this->key() !== '' && $this->listId() > 0;
    }

    /**
     * Push a subscriber to Brevo. Returns true when Brevo acknowledged it.
     */
    public function sync(NewsletterSubscriber $subscriber): bool
    {
        $apiKey = $this->key();
        $listId = $this->listId();

        if ($apiKey === '' || $listId <= 0) {
            Log::warning('Brevo sync skipped: no API key or list id. Add them in Settings → Newsletter.');

            return false;
        }

        $payload = [
            'email' => $subscriber->email,
            'listIds' => [(int) $listId],
            // Re-subscribing an existing contact should update rather than 400.
            'updateEnabled' => true,
        ];

        $startedAt = microtime(true);

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'api-key' => $apiKey,
                    'accept' => 'application/json',
                ])
                ->asJson()
                ->post((string) config('sharkweb.brevo.endpoint'), $payload);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            // 201 = created, 204 = updated. Both mean success.
            $ok = in_array($response->status(), [200, 201, 204], true);

            $this->log($subscriber, $response->status(), $response->body(), $durationMs);

            if ($ok) {
                $subscriber->update([
                    'brevo_synced' => true,
                    'is_verified' => true,
                ]);

                return true;
            }

            Log::warning('Brevo rejected the contact.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (Throwable $e) {
            Log::warning('Brevo transport failure.', ['error' => $e->getMessage()]);

            $this->log($subscriber, null, $e->getMessage(), (int) round((microtime(true) - $startedAt) * 1000));

            return false;
        }
    }

    /**
     * Retry every locally-stored subscriber that never reached Brevo.
     *
     * Intended for a scheduled command, since there is no queue worker on shared hosting to
     * do it automatically.
     *
     * @return array{synced: int, failed: int}
     */
    public function syncPending(int $limit = 50): array
    {
        $synced = 0;
        $failed = 0;

        NewsletterSubscriber::query()
            ->pendingSync()
            ->limit($limit)
            ->get()
            ->each(function (NewsletterSubscriber $subscriber) use (&$synced, &$failed): void {
                $this->sync($subscriber) ? $synced++ : $failed++;
            });

        return ['synced' => $synced, 'failed' => $failed];
    }

    private function log(NewsletterSubscriber $subscriber, ?int $status, ?string $body, int $durationMs): void
    {
        try {
            WebhookLog::record(
                event: 'brevo.subscriber_sync',
                // The email address is personal data; log the row id instead.
                payload: ['subscriber_id' => $subscriber->id],
                status: $status,
                body: $body,
                durationMs: $durationMs,
            );
        } catch (Throwable $e) {
            Log::warning('Could not write Brevo webhook log.', ['error' => $e->getMessage()]);
        }
    }
}
