<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\ResolvesLeadContact;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The invitation a lead receives once the owner approves them (§14.11, second half).
 *
 * WHY THIS IS A SECOND EMAIL AND NOT PART OF THE ACKNOWLEDGEMENT. The auto-responder goes out
 * the moment anyone submits the form, before a human has read a word. This one only goes when
 * the owner presses a button, which is the whole point: it says "we looked at yours and we
 * want to talk", and that sentence is worthless if a machine says it to everybody.
 *
 * THE BOOKING LINK IS A SETTING. `general.booking_url` holds the Cal.com event; the lead's
 * name and email are appended so the calendar opens with them already filled in. A renamed
 * handle is then a panel edit, not a deploy. The URL, the greeting and the contact details
 * come from `ResolvesLeadContact`, shared with the reminder email.
 *
 * CONTENT RULE — the same as the acknowledgement: only what `docs/existingwebsitecontents.md`
 * documents. The free 30-minute consultation, what it covers, and nothing else.
 *
 * NOT QUEUED — shared hosting runs no queue worker, so the caller sends synchronously and
 * wraps it in a try/catch.
 */
class QualifiedInvite extends Mailable
{
    use Queueable, ResolvesLeadContact, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'re a fit — book your 30 minutes',
            // A reply goes to a monitored inbox, not to noreply@.
            replyTo: [$this->contactEmail()],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lead-qualified',
            with: [
                'lead' => $this->lead,
                'firstName' => $this->firstName(),
                'bookingUrl' => $this->bookingUrl(),
                'contactEmail' => $this->contactEmail(),
                'whatsappLink' => 'https://wa.me/'.$this->whatsappNumber(),
                'whatsappDisplay' => $this->whatsappDisplay(),
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
}
