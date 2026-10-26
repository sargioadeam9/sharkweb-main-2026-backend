<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates an inbound lead submission (§Module A, §4).
 *
 * The frontend already validates with Zod, but that check happens in the Next.js process,
 * which is a different machine on a different platform. This is the authoritative one:
 * anything reaching this FormRequest is already past the proxy, so nothing upstream can be
 * trusted to have validated it.
 *
 * SANITISATION STRATEGY
 * Input is stripped rather than escaped. These values are stored and later rendered in the
 * admin panel and exported to CSV — escaping at input time would double-encode them once
 * Filament escapes on output. Removing tags and control characters, then letting the
 * renderer escape, is the correct order.
 */
class StoreLeadRequest extends FormRequest
{
    /** The route is behind `api.key`, so authorisation is already settled. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'goal' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:2000'],

            // The visitor's IP, forwarded by the Next.js action. Validated as an IP so a
            // caller cannot store arbitrary text in an indexed column.
            'ip_address' => ['nullable', 'ip'],

            // Attribution (§14.1). Optional today — the frontend does not send these yet.
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'utm_term' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:100'],
            'referrer_url' => ['nullable', 'string', 'max:500'],
            // Meta's click and browser identifiers, captured client-side. Opaque strings,
            // never displayed, only forwarded to the Conversions API.
            'fbc' => ['nullable', 'string', 'max:255'],
            'fbp' => ['nullable', 'string', 'max:255'],
            'source_page' => ['nullable', 'string', 'max:50'],

            // Meta CAPI deduplication key (§14.4).
            'event_id' => ['nullable', 'string', 'max:64'],

            // Honeypot. Present in the schema so a filled value is visible to the
            // controller (which drops the submission silently) rather than rejected as an
            // unknown field, which would tell a bot exactly what tripped it.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'name.min' => 'Please enter your full name.',
            'email.required' => 'We need an email address to reply to.',
            'email.email' => 'That email address does not look valid.',
            'message.max' => 'That message is a little long — please keep it under 2000 characters.',
        ];
    }

    /**
     * Cleaned input, ready for the service layer.
     *
     * @return array<string, mixed>
     */
    public function leadData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $this->clean($validated['name'] ?? ''),
            'email' => Str::lower(trim($validated['email'] ?? '')),
            'phone' => $this->clean($validated['phone'] ?? null),
            'goal' => $this->clean($validated['goal'] ?? null) ?: 'Growth Audit',
            'message' => $this->clean($validated['message'] ?? null),
            'ip_address' => $this->clientIp(),
            'source_page' => $this->clean($validated['source_page'] ?? null),
            'utm_source' => $this->clean($validated['utm_source'] ?? null),
            'utm_medium' => $this->clean($validated['utm_medium'] ?? null),
            'utm_campaign' => $this->clean($validated['utm_campaign'] ?? null),
            'utm_term' => $this->clean($validated['utm_term'] ?? null),
            'utm_content' => $this->clean($validated['utm_content'] ?? null),
            'referrer_url' => $this->clean($validated['referrer_url'] ?? null),
            'fbc' => $this->clean($validated['fbc'] ?? null),
            'fbp' => $this->clean($validated['fbp'] ?? null),
            'event_id' => $this->clean($validated['event_id'] ?? null),
            // Not stored: the browser's UA is only used for the server-side event's match
            // quality. Kept in this array so it travels with the submission explicitly
            // rather than being read from the request deep inside a service.
            'user_agent' => mb_substr((string) $this->userAgent(), 0, 500) ?: null,
        ];
    }

    /**
     * Whether the honeypot was filled.
     *
     * The controller answers with a normal success response either way, so an automated
     * caller cannot tell the difference between "stored" and "dropped".
     */
    public function isHoneypotTripped(): bool
    {
        return filled($this->input('website'));
    }

    /**
     * The visitor's IP.
     *
     * The body value is preferred because that is the *visitor's* address — this request
     * arrives from the Next.js server, so `$request->ip()` would otherwise record Vercel's
     * address for every lead and make the IP-based rate limiter meaningless.
     *
     * This relies on the API key: only our own frontend can set it, so the value is
     * trusted to the same degree the key is. If this endpoint ever becomes multi-tenant,
     * this needs revisiting.
     */
    private function clientIp(): ?string
    {
        return $this->validated()['ip_address'] ?? $this->ip();
    }

    /**
     * Strip tags and control characters, collapse runaway whitespace.
     *
     * `strip_tags` rather than `htmlspecialchars` — see the class docblock. Newlines are
     * preserved for the message field because the admin panel renders them.
     */
    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Remove anything that looks like a tag, then any remaining control characters
        // except tab/newline/carriage-return.
        $stripped = strip_tags($value);
        $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $stripped) ?? $stripped;

        // Collapse 3+ consecutive newlines down to 2.
        $stripped = preg_replace("/\n{3,}/", "\n\n", $stripped) ?? $stripped;

        $trimmed = trim($stripped);

        return $trimmed === '' ? null : $trimmed;
    }
}
