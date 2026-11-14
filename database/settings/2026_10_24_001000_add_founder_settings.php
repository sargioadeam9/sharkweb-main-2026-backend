<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The founder card's own contact surface, and the removal of four dead settings.
 *
 * WHY THE FOUNDER FIELDS EXIST. `components/about/FounderCard.tsx` hardcoded three links:
 * a LinkedIn URL, `mailto:info@zonaedhossain.com` and `tel:+8801896444400`. The email had
 * already drifted away from the studio inbox in `general.contact_email`, which is the exact
 * failure mode of a value that lives in two places. The owner also asked for an upload for
 * the portrait, which cannot live in a `.ts` file at all.
 *
 * WHY FOUR SETTINGS GO. `site_tagline`, `availability_status`, `facebook_url` and
 * `youtube_url` were served by the API and rendered by nothing: the footer social band has
 * artwork for three networks, and the tagline/availability slots disappeared with the
 * frontend rebuild. The owner's instruction (2026-10-24) was to remove rather than keep
 * pretending they do something.
 *
 * The rows are deleted, not left orphaned. A settings row with no property on the class is
 * harmless to Spatie, but it is also invisible — which is how a dead field survives for
 * months.
 */
return new class extends SettingsMigration
{
    /** @var array<int, string> */
    private const REMOVED = [
        'general.site_tagline',
        'general.availability_status',
        'general.facebook_url',
        'general.youtube_url',
    ];

    public function up(): void
    {
        // The founder's own address, as published on the About page before this migration.
        $this->migrator->add('general.founder_email', 'info@zonaedhossain.com');

        // Stored as displayed. The `tel:` href strips the spaces itself, so the owner can
        // type the number however he says it out loud.
        $this->migrator->add('general.founder_phone', '+880 1896 444400');

        $this->migrator->add('general.founder_linkedin_url', 'https://www.linkedin.com/in/zonaedhossain/');

        // The portrait the About page already ships, so nothing changes on the first deploy.
        $this->migrator->add('general.founder_avatar_path', '/img/brand/zonaed-portrait.webp');

        foreach (self::REMOVED as $key) {
            $this->migrator->deleteIfExists($key);
        }
    }

    public function down(): void
    {
        foreach ([
            'general.founder_email',
            'general.founder_phone',
            'general.founder_linkedin_url',
            'general.founder_avatar_path',
        ] as $key) {
            $this->migrator->deleteIfExists($key);
        }

        $this->migrator->add('general.site_tagline', 'Marketing that hunts down growth. Not luck.');
        $this->migrator->add('general.availability_status', 'Available for projects Q3 2026');
        $this->migrator->add('general.facebook_url', null);
        $this->migrator->add('general.youtube_url', null);
    }
};
