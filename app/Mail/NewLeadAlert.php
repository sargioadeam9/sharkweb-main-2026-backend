<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Admin notification for a new lead (§14.8).
 *
 * Sent through Resend's HTTP API, not SMTP, and therefore NOT queued — shared cPanel
 * hosting has no queue worker (docs/BACKEND_MVP_PLAN.md §4), so a queued mail would sit in the
 * jobs table forever.
 *
 * The Telegram alert is the fast path; this is the durable record that survives someone
 * muting a phone.
 */
class NewLeadAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
    ) {}

    public function envelope(): Envelope
    {
        $score = $this->lead->lead_score?->getLabel() ?? 'New';

        return new Envelope(
            // Score in the subject line so the inbox preview alone is enough to triage.
            subject: "[{$score}] New enquiry — {$this->lead->displayName()}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-lead',
            with: [
                'lead' => $this->lead,
                'attribution' => $this->attributionLine(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    private function attributionLine(): ?string
    {
        if (! $this->lead->hasAttribution()) {
            return null;
        }

        return collect([
            $this->lead->utm_source,
            $this->lead->utm_medium,
            $this->lead->utm_campaign,
        ])->filter()->implode(' / ');
    }
}
