<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The Brevo credential, moved out of `.env` and into the panel.
 *
 * The owner's instruction was "add it in settings so I can change it anytime", and the reason
 * is on the record: the key was empty, nothing synced, and the panel gave no sign of it. A
 * credential that needs a server edit to fix is a credential that stays broken quietly.
 *
 * `.env` remains the fallback so a fresh install works before anyone opens the panel.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.brevo_api_key', null);
        $this->migrator->add('general.brevo_list_id', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.brevo_api_key');
        $this->migrator->deleteIfExists('general.brevo_list_id');
    }
};
