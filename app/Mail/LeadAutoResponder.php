<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lead;
use App\Settings\GeneralSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Acknowledgement sent to the person who submitted the contact form (§14.11).
 *
 * The agency is already alerted by NewLeadAlert; this is the other half of the exchange.
 * The site promises "we reply within 24 hours", and an instant acknowledgement is what
 * makes that promise credible — without it the enquirer hears nothing overnight and keeps
 * shopping.
 *
 * CONTENT RULE — this email may promise only what `docs/existingwebsitecontents.md` documents:
 *
 *   - "Same team. We reply within 24 hours."
 *   - the free 30-minute consultation, which maps the funnel, names the biggest leak and
 *     outlines the highest-leverage fix
 *   - the Funnel Scorecard lead magnet ("a one-page checklist we use in kickoffs —
 *     traffic, offer, page, follow-up")
 *
 * No invented turnaround guarantee, no pricing, no deliverable that is not in the source.
 *
 * NOT QUEUED. Shared cPanel has no queue worker (docs/BACKEND_MVP_PLAN.md §4), so this is a
 * direct synchronous send through Resend, and the caller wraps it in a try/catch.
 */
class LeadAutoResponder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We have your message — The Shark Web',
            // A reply goes straight to a monitored inbox, not to noreply@.
            replyTo: [$this->contactEmail()],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lead-auto-responder',
            with: [
                'lead' => $this->lead,
                'firstName' => $this->firstName(),
                // The two rows in the enquiry summary. Both come from what the visitor
                // actually typed; either can be null, and the view drops the row rather
                // than printing an empty one.
                'engagement' => $this->engagement(),
                'timeline' => $this->timeline(),
                'contactEmail' => $this->contactEmail(),
                'contactWhatsApp' => $this->whatsappNumber(),
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

    /**
     * The recipient's first name, with a neutral fallback.
     *
     * "Hi there" is deliberate — a lead stored without a usable name must not produce
     * "Hi ," in a client-facing email.
     *
     * AN ALL-LOWERCASE NAME GETS CAPITALISED. "Got it, adeam." reads like a template that
     * dropped the field in, and a phone keyboard auto-capitalises for nobody. Only a name
     * with no capitals at all is touched: "McDonald", "de Vries" and "Zonaed" are left
     * exactly as the person wrote them.
     */
    private function firstName(): string
    {
        $name = trim((string) $this->lead->name);

        if ($name === '') {
            return 'there';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = $parts[0] ?? '';

        if ($first === '') {
            return 'there';
        }

        return $first === mb_strtolower($first)
            ? mb_convert_case($first, MB_CASE_TITLE, 'UTF-8')
            : $first;
    }

    /**
     * What the visitor said they wanted, if the form asked them.
     *
     * The door-specific field is `engagement` on the form and lands in `goal` on the lead.
     */
    private function engagement(): ?string
    {
        $goal = trim((string) $this->lead->goal);

        return $goal !== '' ? $goal : null;
    }

    /**
     * The timeline, dug out of the message.
     *
     * THE FORM HAS NO COLUMN FOR IT. `app/actions/submit-lead.ts` packs the door-specific
     * answers into `message` as "Label: value" lines (Timeline, Budget, Stack, Link,
     * Engineers) because the API has nowhere else to put them. This reads the same format
     * the frontend writes, and returns null when the visitor never answered — the view then
     * omits the row instead of showing an empty one.
     */
    private function timeline(): ?string
    {
        $message = (string) $this->lead->message;

        if (preg_match('/^[ \t]*Timeline:[ \t]*(.+)$/mi', $message, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1]);

        return $value !== '' ? $value : null;
    }

    /**
     * The studio inbox, from the panel first and `.env` second.
     *
     * Both sources currently agree; reading the panel first means changing the address there
     * also changes the address this email replies to, which is the point of the setting.
     */
    private function contactEmail(): string
    {
        $email = (string) app(GeneralSettings::class)->contact_email;

        return $email !== '' ? $email : (string) config('sharkweb.contact.email');
    }

    /** Digits only, as `wa.me` needs them. */
    private function whatsappNumber(): string
    {
        $raw = (string) app(GeneralSettings::class)->whatsapp_number;

        if ($raw === '') {
            $raw = (string) config('sharkweb.contact.whatsapp');
        }

        return preg_replace('/\D/', '', $raw) ?? '';
    }

    /**
     * The number as a human reads it: +880 1896 444400.
     *
     * Formatted rather than stored, so the panel can keep the plain digits `wa.me` wants and
     * the email still shows a number a person can read out.
     */
    private function whatsappDisplay(): string
    {
        $digits = $this->whatsappNumber();

        if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
            return '+880 '.substr($digits, 3, 4).' '.substr($digits, 7);
        }

        return $digits !== '' ? '+'.$digits : '';
    }

}
