<?php

declare(strict_types=1);

namespace App\Settings;

use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

/**
 * Global site configuration (§Module D).
 *
 * Stored through spatie/laravel-settings rather than a hand-rolled key/value table, which
 * means every value is typed and defaults are guaranteed — the API's /settings endpoint can
 * serialise this class directly without defensive null checks on the frontend.
 *
 * Registered in config/settings.php. Values are edited from the Filament settings page and
 * served to Next.js at GET /api/v1/settings.
 */
class GeneralSettings extends Settings
{
    /* ---- Identity ---- */
    public string $site_name;

    /* ---- Contact ---- */
    public string $contact_email;

    public string $contact_phone;

    /** Digits only, used to build the wa.me link. */
    public string $whatsapp_number;

    public string $contact_location;

    /**
     * The booking page the "Approve & invite" email sends a qualified lead to.
     *
     * A SETTING, NOT A CONSTANT. A calendar link is the kind of thing that changes when an
     * account moves or a handle is renamed, and a dead booking button in a client-facing
     * email is worse than no button. The name and email are appended to it at send time so
     * the lead arrives at the calendar already identified.
     */
    public string $booking_url;

    /* ---- Social ---- */
    public ?string $twitter_url = null;

    public ?string $linkedin_url = null;

    public ?string $instagram_url = null;

    /**
     * Back in the model on the owner's instruction (2026-10-25): the footer now has a Facebook
     * card. It was removed a day earlier because the social band had artwork for three
     * networks and this one had nowhere to appear.
     */
    public ?string $facebook_url = null;

    public ?string $github_url = null;

    /* ---- Founder ---- */

    /**
     * The founder's own direct channels, shown in the About page's founder card.
     *
     * SEPARATE FROM THE `contact` BLOCK ON PURPOSE. `contact_email` is the studio inbox
     * (`info@thesharkweb.com`); this is the address that reaches the person. They were
     * hardcoded in `FounderCard.tsx` and had already drifted to a different domain, which
     * is exactly what happens to a value that lives in two places.
     */
    public string $founder_email;

    public string $founder_phone;

    public ?string $founder_linkedin_url = null;

    /**
     * A public path, either `/img/...` (a bundled asset) or `founder/...` (an upload on the
     * `public` disk). `founderAvatarUrl()` resolves both shapes to one URL.
     */
    public string $founder_avatar_path;

    /* ---- Daily email report ---- */

    /**
     * Whether the nightly KPI digest is emailed at 23:30 Asia/Dhaka.
     *
     * Toggled on the Settings page. The command refuses to send when this is false —
     * except with `--force`, which is what the page's "send test email" button uses.
     */
    public bool $daily_report_enabled = true;

    /** Where the nightly report goes. Editable so it never needs a deploy. */
    public string $daily_report_recipient = 'info@thesharkweb.com';

    /**
     * The Resend credential the nightly report is sent with.
     *
     * ENCRYPTED AT REST, and deliberately absent from `toFrontendArray()` — same rule as the
     * Meta token below. The owner asked to be able to rotate it from the panel rather than
     * by editing `.env` on the server, which is the right instinct: a credential that needs
     * a deploy to change is a credential that stays wrong for a week.
     *
     * `.env` is still the fallback, so a fresh install works before anyone opens the panel.
     */
    #[ShouldBeEncrypted]
    public ?string $resend_api_key = null;

    /* ---- Newsletter (Brevo) ---- */

    /**
     * The Brevo credential the newsletter list is synced with.
     *
     * SAME TREATMENT AS THE RESEND KEY: encrypted at rest, never serialised to the frontend,
     * never rendered back into the form. It moved out of `.env` because a revoked Brevo key
     * used to mean the whole newsletter silently stopped syncing until somebody edited the
     * server — and the panel gave no hint that anything was wrong.
     */
    #[ShouldBeEncrypted]
    public ?string $brevo_api_key = null;

    /**
     * The Brevo list every subscriber is added to.
     *
     * Not a secret, but it belongs beside the key: half a Brevo configuration is not a
     * configuration, and the two are always changed together.
     */
    public ?string $brevo_list_id = null;

    /* ---- SEO defaults ---- */
    public string $default_meta_title;

    public string $default_meta_description;

    public ?string $default_og_image = null;

    /* ---- Analytics ---- */
    public ?string $ga4_measurement_id = null;

    public ?string $gtm_container_id = null;

    public ?string $meta_pixel_id = null;

    /**
     * Whether server-side Meta events are sent at all.
     *
     * The env flag `META_CAPI_ENABLED` still exists as the fallback for a fresh install, but
     * this is the switch the owner actually uses — being unable to turn server-side tracking
     * off without a deploy is how a broken token stays broken for a week.
     */
    public bool $meta_capi_enabled = false;

    /**
     * The Meta Conversions API credential.
     *
     * ENCRYPTED AT REST, and deliberately absent from `toFrontendArray()`. With this value
     * anyone can write events into the pixel, so it is a credential in the same sense an API
     * key is — it must never appear in an API response, a page source or a log line.
     */
    #[ShouldBeEncrypted]
    public ?string $meta_capi_access_token = null;

    /**
     * Routes Meta events into the Test Events tool instead of the live dataset.
     *
     * Must be empty in normal operation: leaving a code in place means real conversions stop
     * counting while the dashboard still looks healthy.
     */
    public ?string $meta_capi_test_event_code = null;

    public ?string $tiktok_pixel_id = null;

    public bool $tiktok_events_api_enabled = false;

    /** TikTok Events API credential. Encrypted at rest, never serialised. See the Meta note. */
    #[ShouldBeEncrypted]
    public ?string $tiktok_access_token = null;

    /* ---- Support widget ---- */
    /** Whether the floating support widget renders on the public site at all. */
    public bool $support_widget_enabled;

    /** The status pill text — "Online" as a rule, but the owner may want "Away". */
    public string $support_status_label;

    public string $support_offer;

    public string $support_intro;

    public string $support_name;

    /**
     * The role shown on the widget. Deliberately NOT `identity.ts`'s `FOUNDER_ROLE`:
     * `docs/existingwebsitecontents.md` documents "Founder & Growth Lead", and the owner chose a
     * different title for this one surface. See the settings migration for the reasoning.
     */
    public string $support_role;

    /** The blue tick beside the name. */
    public bool $support_verified;

    /* ---- Footer ---- */
    public string $footer_blurb;

    public string $copyright_text;

    public static function group(): string
    {
        return 'general';
    }

    /**
     * One URL for the founder portrait, whichever way it was set.
     *
     * ABSOLUTE FOR AN UPLOAD, RELATIVE FOR A BUNDLED ASSET, and the difference is not
     * cosmetic. An upload lands on the backend's `public` disk, and the Next app has no
     * `/storage` symlink — a relative `/storage/founder/x.webp` returns 404 from the site
     * while returning 200 from the API. Probed both before changing this.
     *
     * `Storage::disk('public')->url()` builds it from the API's own APP_URL, so it is
     * `http://127.0.0.1:8000/storage/...` here and the API host in production. An `<img>`
     * needs no CORS for that, and the browser caches it like any other asset.
     */
    public function founderAvatarUrl(): string
    {
        $path = trim($this->founder_avatar_path);

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // A bundled asset is served by the frontend itself, so it stays relative.
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Shape served to the frontend at GET /api/v1/settings.
     * Kept here so the API Resource stays a thin serialiser.
     *
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'site_name' => $this->site_name,
            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
                'whatsapp' => $this->whatsapp_number,
                'location' => $this->contact_location,
            ],
            'social' => array_filter([
                'twitter' => $this->twitter_url,
                'linkedin' => $this->linkedin_url,
                'instagram' => $this->instagram_url,
                'facebook' => $this->facebook_url,
                'github' => $this->github_url,
            ], fn (?string $url): bool => filled($url)),
            'founder' => [
                'email' => $this->founder_email,
                'phone' => $this->founder_phone,
                'linkedin' => $this->founder_linkedin_url,
                // Resolved here, not in the browser: an upload lands on the `public` disk as
                // `founder/xxx.webp`, which is served from `/storage/`, while a bundled asset
                // is already a `/img/...` path. Two shapes, one URL.
                'avatar' => $this->founderAvatarUrl(),
            ],
            'seo' => [
                'default_title' => $this->default_meta_title,
                'default_description' => $this->default_meta_description,
                'default_og_image' => $this->default_og_image,
            ],
            /*
             * PUBLIC IDs ONLY. `meta_capi_access_token` and `tiktok_access_token` are
             * deliberately absent and must stay that way: this array is serialised to
             * Next.js at GET /api/v1/settings and rendered into page source. The tokens
             * are server-side credentials — if one is ever needed here, that is a signal
             * the event is being sent from the wrong side of the wire.
             *
             * The toggles are absent for the same reason: whether server-side events are
             * enabled is not something the browser needs to know.
             */
            'analytics' => array_filter([
                'ga4_measurement_id' => $this->ga4_measurement_id,
                'gtm_container_id' => $this->gtm_container_id,
                'meta_pixel_id' => $this->meta_pixel_id,
                'tiktok_pixel_id' => $this->tiktok_pixel_id,
            ], fn (?string $id): bool => filled($id)),
            'footer' => [
                'blurb' => $this->footer_blurb,
                'copyright' => $this->copyright_text,
            ],
            /*
             * The floating support widget.
             *
             * NO CONTACT DETAILS HERE, on purpose. The widget reads the `contact` block above,
             * so the email address and the WhatsApp number each have exactly one home — two
             * copies of a phone number are two places to forget to update.
             *
             * `enabled` is always present rather than the key being omitted when false: an
             * absent key is indistinguishable from a backend that could not be reached, and
             * the frontend has to treat those two cases differently.
             *
             * NO `avatar_path`. The widget shows the same face as the About page, so it
             * reads the `founder` block above. Two settings holding one portrait is how the
             * two surfaces drift apart, and this project has already paid for that lesson
             * three times (email, phone, job title).
             */
            'support' => [
                'enabled' => $this->support_widget_enabled,
                'status_label' => $this->support_status_label,
                'offer' => $this->support_offer,
                'intro' => $this->support_intro,
                'name' => $this->support_name,
                'role' => $this->support_role,
                'verified' => $this->support_verified,
            ],
        ];
    }
}
