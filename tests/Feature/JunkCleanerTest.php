<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Maintenance\JunkCleanerService;
use Tests\TestCase;

/**
 * The junk cleaner's safety model, tested where it can actually fail.
 *
 * The whitelist IS the product — a cleaner that deletes the wrong file on a shared
 * host destroys the business, so every test here guards a path that must survive.
 *
 * Tests touch the real storage directory (storage_path() is not virtualised), so
 * every file a test creates is registered and removed in tearDown().
 */
class JunkCleanerTest extends TestCase
{
    private JunkCleanerService $service;

    /** Files this test created and removes again afterwards. */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new JunkCleanerService;
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    /* ------------------------------------------------------------------ *
     * The never-touch guard
     * ------------------------------------------------------------------ */

    public function test_protected_paths_are_recognised_in_every_spelling(): void
    {
        foreach ([
            base_path('.env'),
            base_path('.env.backup'),
            base_path('database'.DIRECTORY_SEPARATOR.'database.sqlite'),
            base_path('storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'photo.jpg'),
            base_path('storage/app/private/ledger.pdf'),
            base_path('public/uploads/banner.png'),
            // Relative spellings from a caller that forgot to absolutise.
            'database/database.sqlite',
            'storage/app/public/media/img.png',
            'public/uploads/x.png',
            '.env.production',
        ] as $path) {
            $this->assertTrue(
                JunkCleanerService::isProtected($path),
                "isProtected() must block [{$path}]."
            );
        }
    }

    public function test_whitelisted_junk_paths_are_not_protected(): void
    {
        foreach ([
            storage_path('logs/laravel-2020-01-01.log'),
            storage_path('framework/views/compiled.php'),
            storage_path('framework/cache/data/aa/bb'),
            storage_path('framework/sessions/session-token'),
            storage_path('app/backup-temp/dump.sql'),
        ] as $path) {
            $this->assertFalse(
                JunkCleanerService::isProtected($path),
                "isProtected() must allow [{$path}]."
            );
        }
    }

    /** Only .env-prefixed basenames trip the .env guard — laravel.log must not. */
    public function test_the_dotenv_guard_only_catches_dotenv_basenames(): void
    {
        $this->assertTrue(JunkCleanerService::isProtected(base_path('.env.backup')));
        $this->assertFalse(JunkCleanerService::isProtected(storage_path('logs/laravel.log')));
    }

    /* ------------------------------------------------------------------ *
     * Scan
     * ------------------------------------------------------------------ */

    public function test_scan_reports_rotated_and_legacy_logs_but_not_todays(): void
    {
        $old = $this->fakeFile(storage_path('logs/laravel-2020-01-01.log'));
        $legacy = $this->fakeFile(storage_path('logs/laravel.log'));
        $today = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        $createdToday = ! is_file($today);

        if ($createdToday) {
            file_put_contents($today, "today\n");
        }

        try {
            $categories = collect($this->service->scan())->keyBy('key');

            $this->assertTrue($categories->has('rotated_logs'), 'Rotated logs must form a category.');

            $paths = array_column($categories['rotated_logs']['items'], 'path');

            $this->assertContains($old, $paths, 'The rotated log must be offered for cleaning.');
            $this->assertContains($legacy, $paths, 'The legacy laravel.log must be offered.');
            $this->assertNotContains($today, $paths, "The current day's log must always stay.");
        } finally {
            if ($createdToday) {
                @unlink($today);
            }
        }
    }

    public function test_scan_offers_backup_temp_leftovers(): void
    {
        $leftover = $this->fakeFile(storage_path('app/backup-temp/dangling-dump.sql'));

        $categories = collect($this->service->scan())->keyBy('key');

        $this->assertTrue($categories->has('backup_temp'), 'A leftover restore dump must appear in the scan.');
        $this->assertContains($leftover, array_column($categories['backup_temp']['items'], 'path'));
    }

    public function test_scan_hides_empty_categories(): void
    {
        foreach ($this->service->scan() as $category) {
            $this->assertNotSame([], $category['items'], 'Empty categories must not reach the UI.');
        }
    }

    /* ------------------------------------------------------------------ *
     * Clean
     * ------------------------------------------------------------------ */

    public function test_clean_removes_a_category_and_reports_bytes_freed(): void
    {
        $stash = $this->stashRealFiles(storage_path('app/backup-temp'));
        $leftover = $this->fakeFile(storage_path('app/backup-temp/dangling-dump.sql'), 'junk');

        try {
            $freed = $this->service->clean('backup_temp');

            $this->assertGreaterThanOrEqual(strlen('junk'), $freed);
            $this->assertFileDoesNotExist($leftover);
        } finally {
            $this->restoreStashed($stash);
        }
    }

    public function test_clean_keeps_todays_log(): void
    {
        $today = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        $createdToday = ! is_file($today);

        /*
         * clean('rotated_logs') deletes EVERYTHING in the category, including the real
         * dev logs (the 93MB legacy laravel.log is the owner's demo target). Stash the
         * real files aside for the duration and put them back afterwards. Today's log
         * must be written AFTER the stash so the stash does not move it.
         */
        $stash = $this->stashRealFiles(storage_path('logs'));
        file_put_contents($today, "today\n");

        try {
            $this->service->clean('rotated_logs');

            $this->assertFileExists($today, "Cleaning rotated logs must never delete the current day's log.");
        } finally {
            if ($createdToday) {
                @unlink($today);
            }

            $this->restoreStashed($stash);
        }
    }

    public function test_clean_of_an_unknown_category_does_nothing(): void
    {
        $this->assertSame(0, $this->service->clean('entire_project'));
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    private function fakeFile(string $path, string $content = 'junk'): string
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $content);
        $this->created[] = $path;

        return $path;
    }

    /**
     * Rename a directory's real files to `<name>.junktest-bak` so a clean() run cannot
     * destroy the dev machine's actual junk (the 93MB legacy log is the demo target).
     *
     * @return array<string, string> renamed-from => renamed-to
     */
    private function stashRealFiles(string $dir): array
    {
        $stash = [];

        if (! is_dir($dir)) {
            return $stash;
        }

        foreach ((array) glob($dir.'/*') as $file) {
            $file = (string) $file;

            if (is_file($file) && ! str_ends_with($file, '.junktest-bak')) {
                $target = $file.'.junktest-bak';
                rename($file, $target);
                $stash[$target] = $file;
            }
        }

        return $stash;
    }

    /** @param array<string, string> $stash */
    private function restoreStashed(array $stash): void
    {
        foreach ($stash as $original => $target) {
            if (is_file($original)) {
                rename($original, $target);
            }
        }
    }
}
