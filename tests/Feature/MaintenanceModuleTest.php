<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageMaintenance;
use App\Models\Lead;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\Maintenance\RestoreService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Backup\BackupDestination\BackupDestination;
use Tests\TestCase;

/**
 * The maintenance module's test suite (§Maintenance).
 *
 * The restore round-trip is the crown jewel: a REAL `backup:run --only-db` through the
 * pure-PHP dumper, then the chunked quote-aware replay — on a database that contains
 * deliberately nasty content (quotes, semicolons, raw newlines inside one SQL string).
 *
 * This file does NOT use RefreshDatabase OR DatabaseMigrations. RefreshDatabase wraps
 * each test in a transaction, which would (a) make `PRAGMA foreign_keys=OFF` a silent
 * no-op and (b) move every restore commit inside a savepoint. DatabaseMigrations adds a
 * migrate:rollback whose `down()`s are another SQLite trap. Instead the connection is
 * pointed at a throwaway FILE database per test — which the pure-PHP dumper also needs,
 * because it opens a second PDO: dumping `:memory:` would read an empty database.
 */
class MaintenanceModuleTest extends TestCase
{
    private User $admin;

    /** Zip files that existed in the backup destination before the restore test ran. */
    private array $preExistingBackups = [];

    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        /* A throwaway FILE database: the pure dumper re-opens it by path, so :memory:
           cannot be used here. */
        $this->dbFile = storage_path('app/maintenance-test-'.getmypid().'.sqlite');
        @unlink($this->dbFile);
        touch($this->dbFile);

        config(['database.connections.sqlite.database' => $this->dbFile]);
        DB::purge('sqlite');

        $this->artisan('migrate')->run();

        $this->admin = User::factory()->create([
            'email' => 'admin@thesharkweb.com',
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->dbFile)) {
            @unlink($this->dbFile);
        }

        /* Remove only the zips THIS process created — the dev machine's real
           backup collection must survive the test suite. */
        if ($this->preExistingBackups !== []) {
            $destination = BackupDestination::create(
                config('backup.backup.destination.disks')[0] ?? 'local',
                config('backup.backup.name'),
            );

            foreach ($destination->backups() as $backup) {
                try {
                    $absolute = $backup->disk()->path((string) $backup->path());
                } catch (\Throwable) {
                    continue;
                }

                if (is_file($absolute) && ! in_array($absolute, $this->preExistingBackups, true)) {
                    @unlink($absolute);
                }
            }
        }

        parent::tearDown();
    }

    /* ------------------------------------------------------------------ *
     * Activity logging
     * ------------------------------------------------------------------ */

    public function test_creating_a_lead_writes_an_activity_row_with_the_causer(): void
    {
        $this->actingAs($this->admin);

        $lead = Lead::create([
            'name' => 'Activity Probe',
            'email' => 'activity@example.com',
            'message' => 'Probe lead',
        ]);

        $row = Activity::query()
            ->where('subject_type', $lead->getMorphClass())
            ->where('subject_id', $lead->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($row, 'Creating a lead must write an activity row.');
        $this->assertSame($this->admin->id, $row->causer_id, 'The authenticated operator must be the causer.');
    }

    public function test_updating_a_lead_logs_only_the_dirty_fields(): void
    {
        $lead = Lead::create([
            'name' => 'Dirty Probe',
            'email' => 'dirty@example.com',
            'status' => 'new',
            'message' => 'Before',
        ]);

        $lead->update(['status' => 'qualified']);

        $row = Activity::query()
            ->where('subject_type', $lead->getMorphClass())
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($row, 'An update must write an activity row.');

        $attributes = $row->properties->get('attributes', []);

        $this->assertArrayHasKey('status', $attributes);
        $this->assertArrayNotHasKey('name', $attributes, 'Unchanged fields must not be logged (logOnlyDirty).');
    }

    /* ------------------------------------------------------------------ *
     * Rollup + retention
     * ------------------------------------------------------------------ */

    public function test_rollup_folds_yesterday_into_daily_counts_and_is_idempotent(): void
    {
        $yesterday = now()->subDay();

        foreach ([1, 2] as $minute) {
            DB::table('activity_log')->insert([
                'log_name' => 'lead',
                'description' => 'Probe '.$minute,
                'event' => 'created',
                'created_at' => $yesterday->copy()->setTime(10, $minute),
                'updated_at' => $yesterday,
            ]);
        }

        DB::table('activity_log')->insert([
            'log_name' => 'settings',
            'description' => 'Settings saved',
            'event' => 'saved',
            'created_at' => $yesterday->copy()->setTime(11, 0),
            'updated_at' => $yesterday,
        ]);

        Artisan::call('sharkweb:activity-rollup');

        $leadRow = DB::table('activity_daily_rollups')
            ->where('day', $yesterday->toDateString())
            ->where('log_name', 'lead')
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($leadRow, 'The lead events must be rolled up by day.');
        $this->assertSame(2, (int) $leadRow->count);

        $settingsRow = DB::table('activity_daily_rollups')
            ->where('day', $yesterday->toDateString())
            ->where('log_name', 'settings')
            ->first();

        $this->assertNotNull($settingsRow);
        $this->assertSame(1, (int) $settingsRow->count);

        /* Re-running must upsert, never double-count. */
        Artisan::call('sharkweb:activity-rollup');

        $this->assertSame(
            2,
            (int) DB::table('activity_daily_rollups')
                ->where('day', $yesterday->toDateString())
                ->where('log_name', 'lead')
                ->where('event', 'created')
                ->value('count'),
            'A re-run must not double-count.'
        );
    }

    public function test_rollup_prunes_raw_rows_past_retention_but_keeps_the_rollups(): void
    {
        /* A rollup that already exists — retention may prune the raw row, never the count. */
        DB::table('activity_daily_rollups')->insert([
            'day' => now()->subDays(200)->toDateString(),
            'log_name' => 'lead',
            'event' => 'created',
            'count' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ancient = now()->subDays(200);
        $fresh = now();

        DB::table('activity_log')->insert([
            'log_name' => 'lead',
            'description' => 'Ancient',
            'event' => 'created',
            'created_at' => now()->subDays(200)->setTime(10, 0),
            'updated_at' => now()->subDays(200),
        ]);

        DB::table('activity_log')->insert([
            'log_name' => 'lead',
            'description' => 'Fresh',
            'event' => 'created',
            'created_at' => $fresh->copy()->setTime(10, 0),
            'updated_at' => $fresh,
        ]);

        Artisan::call('sharkweb:activity-rollup');

        $this->assertSame(
            0,
            DB::table('activity_log')->where('description', 'Ancient')->count(),
            'Raw rows past retention must be pruned.'
        );

        $this->assertSame(
            1,
            DB::table('activity_log')->where('description', 'Fresh')->count(),
            'Fresh raw rows must survive the retention prune.'
        );

        $this->assertSame(
            7,
            (int) DB::table('activity_daily_rollups')->where('day', now()->subDays(200)->toDateString())->value('count'),
            'Rollups are kept forever — the prune must never touch them.'
        );
    }

    public function test_webhook_logs_prune_by_outcome(): void
    {
        /* Successful dispatch, 40 days old — prunable. */
        WebhookLog::create(['event' => 'old-success', 'response_status' => 200]);
        WebhookLog::where('event', 'old-success')->update(['created_at' => now()->subDays(40)]);

        /* Failure of the same age — kept three times longer. */
        WebhookLog::create(['event' => 'old-failure', 'response_status' => 500]);
        WebhookLog::where('event', 'old-failure')->update(['created_at' => now()->subDays(40)]);

        Artisan::call('model:prune', ['--model' => [WebhookLog::class]]);

        $this->assertSame(0, WebhookLog::where('event', 'old-success')->count());
        $this->assertSame(1, WebhookLog::where('event', 'old-failure')->count(), 'Failures are kept 90 days, not 30.');
    }

    /* ------------------------------------------------------------------ *
     * Backup → restore round-trip
     * ------------------------------------------------------------------ */

    /**
     * A real backup through the pure-PHP dumper, then a full chunked replay.
     *
     * The nasty message is the point: apostrophes, doubled quotes, semicolons and raw
     * newlines all appear INSIDE one SQL string literal. A line-based scanner would
     * corrupt it; the quote-aware one must round-trip it byte for byte.
     */
    public function test_a_backup_restores_the_database_exactly(): void
    {
        $this->preExistingBackups = $this->backupZipPaths();

        $nasty = "It's a 'quoted' message.\nLine two with ; a semicolon.\n-- comment-looking line\nLine ''four''";

        Lead::create([
            'name' => 'Survivor',
            'email' => 'survivor@example.com',
            'message' => $nasty,
        ]);

        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);

        $this->assertSame(0, $exitCode, 'backup:run failed: '.Artisan::output());

        $zips = $this->backupZipPaths();
        $newZips = array_values(array_diff($zips, $this->preExistingBackups));

        $this->assertNotEmpty($newZips, 'backup:run must produce a zip.');
        $zipPath = $newZips[0];

        /* A row written after the backup — the restore must make it disappear. */
        Lead::create([
            'name' => 'Ghost',
            'email' => 'ghost@example.com',
            'message' => 'Born after the backup',
        ]);

        /* Snapshot backup-temp so the cleanup assertion only covers what the
           restore itself created. */
        $tempBefore = (array) glob(storage_path('app/backup-temp').'/*');

        $run = app(RestoreService::class)->prepare($zipPath, 'test-safety-backup');

        $guard = 0;

        while ($run->isActive() && $guard++ < 200) {
            $run = app(RestoreService::class)->advance($run);
        }

        $this->assertSame('done', $run->status, 'Restore did not finish: '.($run->error ?? 'unknown'));
        $this->assertGreaterThan(0, $run->total_statements);

        /* The pre-backup row survives, byte for byte. */
        $restored = Lead::query()->where('email', 'survivor@example.com')->first();
        $this->assertNotNull($restored, 'The pre-backup lead must exist after the restore.');
        $this->assertSame($nasty, $restored->message, 'Multi-line quoted content must survive the replay exactly.');

        /* The post-backup row is gone — the restore really rewound the database. */
        $this->assertFalse(
            Lead::query()->where('email', 'ghost@example.com')->exists(),
            'A row created after the backup must be gone after the restore.'
        );

        /* The restore engine cleans up its own extracted dumps. */
        foreach ((array) glob(storage_path('app/backup-temp').'/*') as $leftover) {
            if (! in_array($leftover, $tempBefore, true)) {
                $this->fail('The restore must remove its extracted dump, left behind: '.basename((string) $leftover));
            }
        }
    }

    /** The backups page's list must see the archive backup:run just wrote. */
    public function test_the_backups_page_lists_a_fresh_archive(): void
    {
        Artisan::call('backup:run', ['--only-db' => true]);

        $names = ManageMaintenance::class;

        $this->assertTrue(class_exists($names), 'The backups page must exist.');

        $page = new $names;
        $listed = $page->getBackupsProperty();

        $this->assertNotEmpty($listed, 'The page must list at least the backup just created.');
        $this->assertTrue(
            (bool) collect($listed)->first(fn (array $b) => $b['restorable']),
            'At least one listed backup must be restorable (local disk).'
        );
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    /** @return list<string> absolute paths of every backup zip on the destination disk */
    private function backupZipPaths(): array
    {
        $destination = BackupDestination::create(
            config('backup.backup.destination.disks')[0] ?? 'local',
            config('backup.backup.name'),
        );

        $paths = [];

        foreach ($destination->backups() as $backup) {
            try {
                $absolute = $backup->disk()->path((string) $backup->path());
            } catch (\Throwable) {
                continue;
            }

            if (is_file($absolute)) {
                $paths[] = $absolute;
            }
        }

        return $paths;
    }
}
