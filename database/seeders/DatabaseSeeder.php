<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Full database seed.
 *
 * ORDER MATTERS: portfolios must exist before services, because ServiceSeeder resolves
 * each service's `proof_case_id` against a portfolio slug. Categories must exist before
 * posts for the same reason.
 *
 * WHY MODEL EVENTS STAY ENABLED:
 * The obvious optimisation here is `WithoutModelEvents`, and it breaks the seed. Both
 * `spatie/laravel-sluggable` and `spatie/laravel-tags` generate slugs from a `creating`
 * model event — with events disabled, `tags.slug` (a NOT NULL column) is never populated
 * and the seed dies on an integrity violation. Post's derived `content_blocks` and
 * `reading_time` come from PostObserver in the same way.
 *
 * The trade-off is that seeding fires ISR revalidation and IndexNow submissions for each
 * published row. Both are config-gated (`ISR_ENABLED`, `INDEXNOW_ENABLED`), so a seeding
 * environment can silence them without touching code — and local development has them off.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Shop (placeholder copy — refuses to run outside local/testing)
            ProductSeeder::class,

            // Demo leads (refuses to run outside local/testing)
            DemoLeadSeeder::class,
        ]);

        // The operator account is written directly, not via a seeder class.
        $this->adminUser();
    }


    /**
     * The backoffice operator.
     *
     * Uses a fixed local password so the panel is reachable immediately after a fresh
     * install. The seeder refuses to run outside local/testing, so this account can never
     * be created on production by accident — use `php artisan make:filament-user` there.
     */
    private function adminUser(): ?User
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Admin user skipped: create one with `php artisan make:filament-user`.');

            return null;
        }

        // firstOrCreate, not updateOrCreate. Re-seeding used to reset the operator's
        // password back to `password` every time, which silently locks you out after you
        // have changed it. Now the seed only creates the account if it is missing.
        return User::firstOrCreate(
            ['email' => 'admin@thesharkweb.com'],
            [
                'name' => 'Zonaed Hossain',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::Superadmin,
            ],
        );
    }
}
