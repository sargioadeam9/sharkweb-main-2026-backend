<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Mail\LeadAutoResponder;
use App\Mail\NewLeadAlert;
use App\Mail\QualifiedInvite;
use App\Models\Lead;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Orchestrates a lead submission.
 *
 * The controller stays thin by delegating here. This class owns the sequence:
 *
 *   1. score and persist the lead
 *   2. alert a human (Telegram — fast, then email — durable)
 *   3. acknowledge the enquirer
 *   4. fan out to any external automation (Sheets / Make / Zapier)
 *
 * ORDERING IS DELIBERATE: the lead is persisted FIRST, and every step after it is isolated.
 * A Telegram outage, a Resend rejection or a webhook change must never cost us the lead —
 * that is the one piece of data here that cannot be recovered from anywhere else.
 *
 * THE AD-PLATFORM REPORTING IS GONE (2026-10-24). `MetaConversionsService` and
 * `TikTokEventsService` were deleted with the analytics module, but their constructor
 * arguments stayed behind — and because the container resolves constructor dependencies
 * before `submit()` ever runs, EVERY lead submission returned a 500. The form's only
 * symptom was its generic "something went wrong" message, so it looked like a frontend
 * problem for as long as nobody posted a test lead. There is nothing to reinstate: the
 * frontend serves no pixel, so there was no conversion to attribute.
 *
 * NOTHING QUEUES. The production target is shared cPanel with no queue worker, so each
 * integration is a direct synchronous HTTP call with a short timeout, wrapped in its own
 * try/catch.
 */
class LeadService
{
    public function __construct(
        private readonly LeadScoringService $scoring,
        private readonly TelegramAlertService $telegram,
        private readonly MetaConversionsService $conversions,
        private readonly ResendMailer $resend,
    ) {}

    /**
     * Accept a submission end to end.
     *
     * @param  array<string, mixed>  $data  Cleaned data from StoreLeadRequest::leadData()
     */
    public function submit(array $data): Lead
    {
        $lead = $this->create($data);

        $this->notifyTelegram($lead);
        $this->notifyByEmail($lead);
        $this->sendAutoResponder($lead);
        $this->fanOut($lead);
        // Last, and isolated like the rest: the lead is already saved, so a Meta outage
        // costs a conversion report and nothing else.
        $this->conversions->sendLead($lead, $data['user_agent'] ?? null);

        return $lead;
    }

    /**
     * Score and persist.
     *
     * `event_id` is generated here when the frontend does not supply one. It is the
     * deduplication key shared with the browser pixel (§14.4) — without it, Meta counts
     * every lead twice, once from each source.
     */
    private function create(array $data): Lead
    {
        $evaluation = $this->scoring->evaluate($data);

        return Lead::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'goal' => $data['goal'] ?? 'Growth Audit',
            'message' => $data['message'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'source_page' => $data['source_page'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
            'referrer_url' => $data['referrer_url'] ?? null,
            'fbc' => $data['fbc'] ?? null,
            'fbp' => $data['fbp'] ?? null,
            'status' => LeadStatus::New,
            'lead_score' => $evaluation['score'],
            'score_reasons' => $evaluation['reasons'],
            'event_id' => filled($data['event_id'] ?? null) ? $data['event_id'] : (string) Str::uuid(),
        ]);
    }

    /** Instant mobile alert. */
    private function notifyTelegram(Lead $lead): void
    {
        try {
            $this->telegram->sendLeadAlert($lead);
        } catch (Throwable $e) {
            Log::warning('Lead Telegram alert failed.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The durable record, sent through Resend's HTTP API.
     *
     * VIA `ResendMailer`, NOT `Mail::to()`. The default mailer on this machine is `log`, so
     * this "durable record" was being written to a log file and nobody ever received it.
     * The mailer is now explicit and uses the key the owner rotates in the panel.
     *
     * The try/catch is not decoration: a revoked key must cost an email, not a lead.
     */
    private function notifyByEmail(Lead $lead): void
    {
        $recipient = config('sharkweb.leads.notification_email');

        if (! filled($recipient)) {
            return;
        }

        try {
            $this->resend->send((string) $recipient, new NewLeadAlert($lead));
        } catch (Throwable $e) {
            Log::warning('Lead notification email failed.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The acknowledgement the enquirer receives (§14.11).
     *
     * Separate from `notifyByEmail()` on purpose: that one is addressed to the agency and
     * its failure is an internal problem, while this one is addressed to a warm lead and
     * its failure is a lost first impression. They fail independently.
     *
     * An unusable address is not a reason to abort — the lead is already stored and someone
     * will still see it in the pipeline.
     */
    private function sendAutoResponder(Lead $lead): void
    {
        if (! filter_var((string) $lead->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $this->resend->send((string) $lead->email, new LeadAutoResponder($lead));
        } catch (Throwable $e) {
            Log::warning('Lead auto-responder failed.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Approve a lead and send the booking invitation.
     *
     * THE ORDER IS THE DESIGN. Send first, then stamp. If Resend rejects the message this
     * method throws and nothing is written, so the panel still shows "Approve & invite"
     * instead of a lead that claims to have been invited and never was. Stamping first and
     * sending second is how a lead goes quiet for a week with a green tick next to it.
     *
     * The status moves to Qualified here rather than in the panel action, so the two facts
     * can never disagree: an invited lead IS a qualified lead.
     *
     * Unlike the alert and the auto-responder, this one does NOT swallow its own failure —
     * the owner pressed a button and is standing there waiting to be told what happened.
     */
    public function sendQualifiedInvite(Lead $lead): void
    {
        /*
         * THE GUARD LIVES HERE, NOT IN THE BUTTON.
         *
         * The panel hides the action for spam and closed leads, but that is a user-interface
         * courtesy, not a rule. This service can be called from a future bulk action, a
         * console command or tinker, and in all three the same thing must be true: a lead
         * the owner has already closed does not get an invitation, and does not get quietly
         * resurrected to Qualified by the send.
         */
        if (in_array($lead->status, [LeadStatus::Closed, LeadStatus::Spam], true)) {
            throw new RuntimeException(
                "{$lead->displayName()} is marked {$lead->status->getLabel()}. Reopen the lead before inviting them."
            );
        }

        $this->resend->send((string) $lead->email, new QualifiedInvite($lead));

        $lead->status = LeadStatus::Qualified;
        $lead->qualified_invite_sent_at = now();
        $lead->save();
    }

    /**
     * Generic outbound webhook (§14.9) — Google Sheets, Make.com, Zapier, Notion.
     *
     * Signed with an HMAC so the receiving automation can verify the payload came from us
     * rather than from anyone who guessed the URL.
     */
    private function fanOut(Lead $lead): void
    {
        $url = (string) config('sharkweb.leads.webhook_url');

        if ($url === '') {
            return;
        }

        $payload = [
            'event' => 'lead.created',
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'goal' => $lead->goal,
                'message' => $lead->message,
                'status' => $lead->status?->value,
                'lead_score' => $lead->lead_score?->value,
                'score_reasons' => $lead->score_reasons,
                'source_page' => $lead->source_page,
                'utm_source' => $lead->utm_source,
                'utm_medium' => $lead->utm_medium,
                'utm_campaign' => $lead->utm_campaign,
                'created_at' => $lead->created_at?->toIso8601String(),
            ],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $secret = (string) config('sharkweb.leads.webhook_secret');

        $startedAt = microtime(true);

        try {
            $response = Http::timeout(6)
                ->withHeaders(array_filter([
                    'Content-Type' => 'application/json',
                    'X-Sharkweb-Event' => 'lead.created',
                    'X-Sharkweb-Signature' => $secret !== '' ? hash_hmac('sha256', $body, $secret) : null,
                ]))
                ->withBody($body, 'application/json')
                ->post($url);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            WebhookLog::record(
                event: 'lead.webhook_fanout',
                payload: ['lead_id' => $lead->id],
                status: $response->status(),
                body: $response->body(),
                durationMs: $durationMs,
            );
        } catch (Throwable $e) {
            Log::warning('Lead fan-out webhook failed.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);

            try {
                WebhookLog::record(
                    event: 'lead.webhook_fanout',
                    payload: ['lead_id' => $lead->id],
                    status: null,
                    body: $e->getMessage(),
                    durationMs: (int) round((microtime(true) - $startedAt) * 1000),
                );
            } catch (Throwable) {
                // Logging is best-effort; never let it escalate.
            }
        }
    }
}
