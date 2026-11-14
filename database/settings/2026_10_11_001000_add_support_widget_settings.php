<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The floating support widget's content.
 *
 * WHY THESE ARE SETTINGS AND NOT FRONTEND CONSTANTS. Everything on this widget is something
 * the owner may want to change on a Tuesday afternoon — the offer, whether he is "Online", the
 * photograph, or whether the widget shows at all. None of it belongs in a deploy.
 *
 * THE ROLE IS DELIBERATELY NOT `identity.ts`'s FOUNDER_ROLE.
 * `docs/existingwebsitecontents.md:287` documents "Founder & Growth Lead", and `identity.ts` was
 * written to make every other surface on the site agree on exactly that. The owner chose
 * "Founder & CMO" for this one widget, knowing it introduces a second title — so this is a
 * decision on the record, not drift. If the two are ever meant to converge, change
 * `docs/existingwebsitecontents.md` first and then `identity.ts`, not this file alone.
 *
 * THE CONTACT DETAILS ARE NOT DUPLICATED HERE. The widget reads `general.contact_email` and
 * `general.whatsapp_number`, which are edited on the Contact tab. Two places holding the same
 * phone number is two places to forget to update.
 *
 * Defaults are the documented, already-published values — no invented claims, no invented
 * contact details.
 */
return new class extends SettingsMigration
{
    /** @var array<int, string> */
    private const KEYS = [
        'general.support_widget_enabled',
        'general.support_status_label',
        'general.support_offer',
        'general.support_intro',
        'general.support_name',
        'general.support_role',
        'general.support_avatar_path',
        'general.support_verified',
    ];

    public function up(): void
    {
        $this->migrator->add('general.support_widget_enabled', true);
        $this->migrator->add('general.support_status_label', 'Online');

        // The owner's own words for what the widget offers.
        $this->migrator->add('general.support_offer', '1:1 Free Consultancy');

        // Built from a documented fact, not a promise: docs/existingwebsitecontents.md says
        // "Every engagement starts with a free audit, not a sales deck".
        $this->migrator->add('general.support_intro', 'Every engagement starts with a free audit. Ask me anything.');

        $this->migrator->add('general.support_name', 'Zonaed Hossain');
        $this->migrator->add('general.support_role', 'Founder & CMO');

        // The portrait the live site already uses for the founder on /about and as its OG
        // image, so the widget and the About page show the same face.
        $this->migrator->add('general.support_avatar_path', '/media/zonaedh.webp');

        $this->migrator->add('general.support_verified', true);
    }

    public function down(): void
    {
        foreach (self::KEYS as $key) {
            $this->migrator->deleteIfExists($key);
        }
    }
};
