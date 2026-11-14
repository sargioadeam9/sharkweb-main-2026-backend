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
 * The one nudge, three days after the invitation.
 *
 * WHY IT EXISTS. An invitation is easy to open on a phone and forget by the evening. One
 * follow-up is the difference between "we replied" and "they booked" — and one is the limit,
 * because a second reminder stops being helpful and starts being the thing people mark as
 * spam.
 *
 * IT DOES NOT KNOW WHETHER THEY BOOKED. Cal.com is not wired back into this app, so the
 * command cannot tell a lead who ignored the link from one who picked a slot on Tuesday. The
 * copy says so plainly ("if you've already booked, ignore this") rather than pretending to
 * track something it does not.
 *
 * NOT QUEUED, like every other mail here — no queue worker on shared hosting.
 */
class BookingReminder extends Mailable
{
    use Queueable, ResolvesLeadContact, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Still time for those 30 minutes?',
            replyTo: [$this->contactEmail()],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lead-reminder',
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
