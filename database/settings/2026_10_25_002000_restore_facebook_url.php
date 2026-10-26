<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Facebook comes back.
 *
 * It was deleted on 2026-10-24 along with YouTube, because the footer's social band only had
 * artwork for Instagram, LinkedIn and X — a setting nothing could display. The owner then
 * asked for a fourth card, so the mark exists now and the setting has somewhere to land.
 *
 * Defaulted to the account rather than left empty: the owner named this URL, and an empty
 * setting would silently fall back to the frontend's placeholder with no way to tell the two
 * apart in the panel.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.facebook_url', 'https://www.facebook.com/thesharkweb');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.facebook_url');
    }
};
