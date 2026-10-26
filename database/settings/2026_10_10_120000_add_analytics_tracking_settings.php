<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Analytics and tracking identifiers (§14.3, §14.4).
 *
 * These join `ga4_measurement_id` and `meta_pixel_id`, which were the only two analytics
 * values the settings page carried. Everything needed to finish tracking now lives here
 * rather than in .env, because the entire point is to complete server-side tracking WITHOUT
 * a deploy — which is what backend/.env.example has claimed all along.
 *
 * THE TWO TOKENS ARE ENCRYPTED. They are credentials: the Meta token can write events into
 * the pixel and the TikTok token is the same kind of key. `addEncrypted` puts them through
 * Laravel's encrypter, and `GeneralSettings::toFrontendArray()` never serialises them, so
 * they cannot reach a browser or an API response.
 *
 * Defaults are null/false rather than placeholders. A tracking ID nobody has supplied yet
 * must not be invented — an empty field is honest, a guessed ID reports into somebody
 * else's account.
 */
return new class extends SettingsMigration
{
    /** @var array<int, string> */
    private const KEYS = [
        'general.gtm_container_id',
        'general.meta_capi_enabled',
        'general.meta_capi_access_token',
        'general.meta_capi_test_event_code',
        'general.tiktok_pixel_id',
        'general.tiktok_access_token',
        'general.tiktok_events_api_enabled',
    ];

    public function up(): void
    {
        // Google — GA4 already exists as `ga4_measurement_id`.
        $this->migrator->add('general.gtm_container_id', null);

        // Meta Conversions API.
        //
        // The enabled flag is SEEDED from the env value, not overridden by it later. A
        // deployment that already had META_CAPI_ENABLED=true must not silently stop sending
        // events on the day this migration runs — but from here on the toggle on the settings
        // page is the only switch, because a switch that `.env` can outvote is not a switch.
        $this->migrator->add('general.meta_capi_enabled', (bool) config('sharkweb.meta_capi.enabled', false));
        $this->migrator->addEncrypted('general.meta_capi_access_token', null);
        $this->migrator->add('general.meta_capi_test_event_code', null);

        // TikTok.
        $this->migrator->add('general.tiktok_pixel_id', null);
        $this->migrator->addEncrypted('general.tiktok_access_token', null);
        $this->migrator->add('general.tiktok_events_api_enabled', false);
    }

    public function down(): void
    {
        foreach (self::KEYS as $key) {
            $this->migrator->deleteIfExists($key);
        }
    }
};
