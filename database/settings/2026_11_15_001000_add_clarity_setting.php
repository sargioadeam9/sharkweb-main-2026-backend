<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Microsoft Clarity analytics identifier.
 *
 * Stored in `general` group as `clarity_project_id`. Allows configuring Microsoft Clarity
 * heatmaps and session recording through Filament without requiring a deployment.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.clarity_project_id', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.clarity_project_id');
    }
};
