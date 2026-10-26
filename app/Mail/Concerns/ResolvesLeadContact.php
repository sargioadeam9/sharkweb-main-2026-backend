<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Settings\GeneralSettings;

/**
 * The bits both lead-facing emails need: the greeting, the reply address, the WhatsApp
 * number and the booking link.
 *
 * WHY A TRAIT. `QualifiedInvite` and `BookingReminder` go to the same person about the same
 * appointment, days apart. They already share a design; they must also share the number they
 * print, the inbox a reply reaches and the calendar they send someone to. Three copies of
 * that logic is how the reminder ends up pointing at an old Cal.com handle while the
 * invitation points at the new one.
 *
 * REQUIRES a `$lead` property (typed `App\Models\Lead`) on the using class.
 */
trait ResolvesLeadContact
{
    /**
     * The recipient's first name, capitalised when it was typed in all lowercase.
     *
     * "McDonald" and "de Vries" keep their casing; a name with no capitals at all gets its
     * first letter back, because "Hi adeam" reads like a field that failed to render.
     */
    protected function firstName(): string
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

    /** The studio inbox, from the panel first and `.env` second. */
    protected function contactEmail(): string
    {
        $email = (string) app(GeneralSettings::class)->contact_email;

        return $email !== '' ? $email : (string) config('sharkweb.contact.email');
    }

    /** Digits only, as `wa.me` needs them. */
    protected function whatsappNumber(): string
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
     * Formatted rather than stored, so the panel can keep the plain digits `wa.me` wants.
     */
    protected function whatsappDisplay(): string
    {
        $digits = $this->whatsappNumber();

        if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
            return '+880 '.substr($digits, 3, 4).' '.substr($digits, 7);
        }

        return $digits !== '' ? '+'.$digits : '';
    }

    /**
     * The booking page, with the lead already filled in.
     *
     * Cal.com (like Calendly) reads `name` and `email` from the query string and prefills the
     * form, so the owner sees who is booking before the event exists. Existing query
     * parameters on the configured URL are preserved rather than clobbered.
     */
    protected function bookingUrl(): string
    {
        $base = trim((string) app(GeneralSettings::class)->booking_url);

        // No booking page configured: fall back to the site's own /meet page rather than
        // rendering a button with an empty href.
        if ($base === '') {
            return rtrim((string) config('sharkweb.frontend_url'), '/').'/meet';
        }

        $params = array_filter([
            'name' => trim((string) $this->lead->name),
            'email' => trim((string) $this->lead->email),
        ]);

        if ($params === []) {
            return $base;
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.http_build_query($params);
    }
}
