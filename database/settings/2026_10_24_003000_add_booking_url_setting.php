<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The booking page for the qualified-lead invitation.
 *
 * Defaults to the live Cal.com event the owner supplied, so the first send works before
 * anyone opens the panel. It lives in settings rather than config because a renamed Cal.com
 * handle must not need a deploy to fix.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.booking_url', 'https://cal.com/thesharkweb/30min');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.booking_url');
    }
};
