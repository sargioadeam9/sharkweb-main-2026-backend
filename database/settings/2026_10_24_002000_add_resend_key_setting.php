<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The Resend credential, moved out of `.env` and into the panel.
 *
 * WHY. The owner's question was whether he could change it without a deploy, and the answer
 * should be yes: rotating a mail key is a Tuesday-afternoon job, not a release. `.env` stays
 * as the fallback for a fresh install, but the panel value wins when it is set.
 *
 * ENCRYPTED AT REST via `#[ShouldBeEncrypted]` on the settings class — the same treatment as
 * the Meta and TikTok tokens. It is never serialised to the frontend, never rendered back
 * into the form, and a blank field on save means "keep what is stored", not "erase it".
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.resend_api_key', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.resend_api_key');
    }
};
