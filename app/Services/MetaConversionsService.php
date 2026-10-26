<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side conversion reporting to Meta's Conversions API.
 *
 * WHY IT EXISTS AGAIN. The original implementation was deleted with the analytics module,
 * and the browser pixel alone is not enough: Safari's tracking prevention, iOS, ad blockers
 * and blocked third-party requests all drop a share of client-side events. Those are exactly
 * the conversions an agency is paying for and cannot see. This sends the same event from the
 * server, where none of that applies.
 *
 * IT DOES NOT DOUBLE-COUNT. The event carries the `event_id` that was already stored on the
 * lead and already sent to `fbq()` from the browser. Meta deduplicates browser and server
 * events that share an id, so the pair collapses into one conversion. That is the whole
 * reason `LeadController` hands the id back to the frontend.
 *
 * IT NEVER THROWS AND NEVER BLOCKS THE LEAD. The lead is already persisted before this runs.
 * A bad token, an expired API version or Meta being down must cost a conversion report, not
 * a customer. Every failure is logged and swallowed — the same rule `IsrWebhookService`
 * follows, for the same reason.
 *
 * NOTHING QUEUES. Production is shared cPanel with no worker, so this is a direct
 * synchronous call with a short timeout.
 */
class MetaConversionsService
{
    public function __construct(private readonly GeneralSettings $settings) {}

    /**
     * Report a stored lead as a `Lead` conversion.
     *
     * @param  string|null  $userAgent  the browser's UA, for match quality
     */
    public function sendLead(Lead $lead, ?string $userAgent = null): void
    {
        try {
            $pixelId = trim((string) $this->settings->meta_pixel_id);
            $token = (string) $this->settings->meta_capi_access_token;

            // Three independent reasons to stay quiet: the owner has not turned it on, has
            // not supplied the pixel, or has not supplied the credential. All three are
            // normal states on a site that is not running ads yet.
            if (! $this->settings->meta_capi_enabled || $pixelId === '' || $token === '') {
                return;
            }

            // The pixel id is interpolated into a URL path, so it must be an id.
            if (! preg_match('/^\d{6,20}$/', $pixelId)) {
                Log::warning('Meta CAPI skipped: the pixel id is not a pixel id.');

                return;
            }

            $payload = [
                'data' => [$this->eventFor($lead, $userAgent)],
                // In the body, not the query string: a token in a URL ends up in proxy logs
                // and error reports, and this one can write events into the pixel.
                'access_token' => $token,
            ];

            // Routes the event to the Test Events tool instead of the live dataset. Left
            // empty in normal operation — a code here means real conversions stop counting
            // while the dashboard still looks healthy.
            $testCode = trim((string) $this->settings->meta_capi_test_event_code);

            if ($testCode !== '') {
                $payload['test_event_code'] = $testCode;
            }

            $version = (string) config('sharkweb.meta.graph_version', 'v21.0');
            $url = "https://graph.facebook.com/{$version}/{$pixelId}/events";

            $response = Http::timeout((int) config('sharkweb.meta.timeout', 6))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            if ($response->failed()) {
                Log::warning('Meta CAPI rejected the event.', [
                    'lead_id' => $lead->id,
                    'status' => $response->status(),
                    // Meta puts the reason in the body; the token is never echoed back.
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Meta CAPI call failed.', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * One `Lead` event in the shape the API expects.
     *
     * @return array<string, mixed>
     */
    private function eventFor(Lead $lead, ?string $userAgent): array
    {
        $userData = array_filter([
            // Meta requires these HASHED, lowercased and trimmed, and wrapped in an ARRAY —
            // the payload is `{"em": ["<sha256>"]}`, not `{"em": "<sha256>"}`. A bare string
            // is accepted by the endpoint and then silently never matches, which is the
            // worst kind of failure: the event is counted, the attribution is not.
            'em' => $this->hashedList($lead->email),
            'ph' => $this->hashedList($this->phoneDigits($lead->phone)),
            'fbc' => $lead->fbc ?: null,
            'fbp' => $lead->fbp ?: null,
            'client_ip_address' => $lead->ip_address ?: null,
            'client_user_agent' => $userAgent,
        ], fn ($value): bool => $value !== null && $value !== '');

        return [
            'event_name' => 'Lead',
            'event_time' => ($lead->created_at ?? now())->timestamp,
            // The deduplication key. Same value as the browser's `eventID`.
            'event_id' => $lead->event_id,
            'action_source' => 'website',
            'event_source_url' => $this->sourceUrl($lead),
            'user_data' => $userData,
            'custom_data' => array_filter([
                'content_name' => $lead->goal ?: null,
                'content_category' => $lead->utm_campaign ?: null,
            ], fn ($value): bool => $value !== null && $value !== ''),
        ];
    }

    /**
     * The absolute page the form was submitted from.
     *
     * `source_page` is stored as a path (`/contact/`), and Meta wants a full URL. Falls back
     * to the site root when the path is missing, because an event with no source URL is
     * rejected outright.
     */
    private function sourceUrl(Lead $lead): string
    {
        $base = rtrim((string) config('sharkweb.frontend_url', 'https://thesharkweb.com'), '/');
        $path = trim((string) $lead->source_page);

        if ($path === '') {
            return $base.'/';
        }

        return str_starts_with($path, 'http') ? $path : $base.'/'.ltrim($path, '/');
    }

    /** Digits only, which is the form Meta expects (country code, no plus, no punctuation). */
    private function phoneDigits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    /** SHA-256 of the normalised value, hex encoded. Meta's own recipe. */
    private function hashed(string $value): ?string
    {
        $normalised = mb_strtolower(trim($value));

        return $normalised === '' ? null : hash('sha256', $normalised);
    }

    /**
     * The same hash, in the list shape the API expects.
     *
     * @return list<string>|null
     */
    private function hashedList(string $value): ?array
    {
        $hash = $this->hashed($value);

        return $hash === null ? null : [$hash];
    }
}
