<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * One portrait, one field.
 *
 * The support widget had its own `support_avatar_path` holding the same face the About page
 * already owns in `founder_avatar_path`. Two settings, one photograph, and a placeholder that
 * pointed at `/media/zonaedh.webp` — a file that has never existed on this site. The widget
 * now reads the founder block, so this key goes.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->deleteIfExists('general.support_avatar_path');
    }

    public function down(): void
    {
        $this->migrator->add('general.support_avatar_path', '/img/brand/zonaed-portrait.webp');
    }
};
