<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Seeds the GeneralSettings defaults.
 *
 * This is a settings migration rather than a seeder on purpose: GeneralSettings declares
 * typed properties with no defaults, so loading it against an empty settings table throws.
 * Making the values part of the migration means `php artisan migrate` alone leaves the app
 * in a working state, which matters for a fresh cPanel deploy.
 *
 * Every value here is taken from docs/existingwebsitecontents.md — no invented contact details.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = [
            // Identity
            'general.site_name' => 'The Shark Web',
            'general.site_tagline' => 'Marketing that hunts down growth. Not luck.',
            'general.availability_status' => 'Available for projects Q3 2026',

            // Contact — from docs/existingwebsitecontents.md
            'general.contact_email' => 'info@thesharkweb.com',
            'general.contact_phone' => '+8801896444400',
            'general.whatsapp_number' => '8801896444400',
            'general.contact_location' => 'Serving Clients Worldwide · Remote & On-demand',

            // Social — no documented profiles exist, so these stay empty rather than
            // being guessed. The frontend omits empty links from the footer and sameAs.
            'general.twitter_url' => null,
            'general.linkedin_url' => null,
            'general.facebook_url' => null,
            'general.instagram_url' => null,
            'general.youtube_url' => null,
            'general.github_url' => null,

            // SEO defaults
            'general.default_meta_title' => 'The Shark Web — Marketing that hunts down growth. Not luck.',
            'general.default_meta_description' => 'AI-powered digital marketing, web builds, and ops automation that scale revenue. Paid media, conversion-first web builds and AI automation for eCommerce and SaaS brands.',
            'general.default_og_image' => null,

            // Analytics — left null until real IDs are supplied
            'general.ga4_measurement_id' => null,
            'general.meta_pixel_id' => null,

            // Footer
            'general.footer_blurb' => 'AI-powered digital marketing, web builds, and ops automation that scale revenue.',
            'general.copyright_text' => '© 2026 The Shark Web. All rights reserved.',
        ];

        foreach ($defaults as $key => $value) {
            $this->migrator->add($key, $value);
        }
    }
};
