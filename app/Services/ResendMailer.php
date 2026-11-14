<?php

declare(strict_types=1);

namespace App\Services;

use App\Settings\GeneralSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Every outbound email goes through here, on one credential.
 *
 * WHY THIS CLASS EXISTS. The Resend key now lives in the panel (Settings → Daily report) so
 * the owner can rotate it without a deploy. Three call sites needed it — the lead alert, the
 * lead auto-responder and the nightly report — and resolving "panel first, `.env` second"
 * three times is how one of them ends up reading a stale value six months from now.
 *
 * WHY `config(['resend.api_key' => …])` AND NOT A CONSTRUCTOR ARGUMENT. resend-laravel binds
 * its client as a singleton and reads `config('resend.api_key')` when it is first resolved;
 * there is no supported way to hand a key to `Mail::mailer('resend')` directly. Setting the
 * config before the first send is the documented seam.
 *
 * CAVEAT: in a long-running process (a queue worker, Octane) that singleton would already
 * hold the old client, so a key change needs a restart. This app has no queue worker, and
 * the scheduled command is a fresh process every night.
 *
 * THIS CLASS THROWS. It is not a fail-soft wrapper: each caller already has its own
 * try/catch and its own log message, and collapsing those into one generic "mail failed"
 * line would hide which of the three broke.
 *
 * NOT `final`, unlike a value object, because the failure path is the one that matters and
 * the only way to test it is to swap this out for something that throws. `LeadService` and
 * `MetaConversionsService` are open for the same reason.
 */
class ResendMailer
{
    public function __construct(
        private readonly GeneralSettings $settings,
    ) {}

    /**
     * The credential in use: the panel's, then `.env`.
     *
     * `.env` stays as the fallback so a fresh install can send before anyone opens the panel.
     */
    public function key(): string
    {
        return trim((string) ($this->settings->resend_api_key ?: config('services.resend.key')));
    }

    public function configured(): bool
    {
        return $this->key() !== '';
    }

    /**
     * @throws RuntimeException when no key is configured at all.
     * @throws \Throwable when Resend rejects the message or the network fails.
     */
    public function send(string $to, Mailable $mail): void
    {
        $key = $this->key();

        if ($key === '') {
            throw new RuntimeException('No Resend API key. Add one in the panel under Settings → Daily report, or set RESEND_API_KEY in .env.');
        }

        config(['resend.api_key' => $key]);

        Mail::mailer('resend')->to($to)->send($mail);
    }
}
