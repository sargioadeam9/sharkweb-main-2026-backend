<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The daily KPI email report (§Daily report).
 *
 * ENABLED BY DEFAULT, and that is the point. The owner asked for a nightly summary of the
 * business; a toggle that defaults to off would mean the feature appears broken on the day
 * it ships. Turning it off is one click on the Settings page.
 *
 * The recipient is a setting rather than an env var because changing where a report goes
 * should not need a deploy — and because the address is `info@thesharkweb.com`, which is
 * already documented as the site's inbox.
 */
return new class extends SettingsMigration
{
    /** @var array<int, string> */
    private const KEYS = [
        'general.daily_report_enabled',
        'general.daily_report_recipient',
    ];

    public function up(): void
    {
        $this->migrator->add('general.daily_report_enabled', true);
        $this->migrator->add('general.daily_report_recipient', 'info@thesharkweb.com');
    }

    public function down(): void
    {
        foreach (self::KEYS as $key) {
            $this->migrator->deleteIfExists($key);
        }
    }
};
