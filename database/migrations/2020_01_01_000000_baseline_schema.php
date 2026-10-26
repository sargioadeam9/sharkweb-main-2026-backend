<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The whole schema in one migration (2026-10-23).
 *
 * WHY THIS EXISTS: after the content layer and the shop were removed, forty migrations
 * still described tables that no longer exist, and two of them referenced enums that went
 * with the features. A fresh `migrate` could not run, so the history was squashed into
 * this one file plus `database/schema/baseline.sql`. The old files are in git history.
 *
 * The DDL lives in the .sql file rather than in a PHP array on purpose: it is SQLite DDL
 * lifted verbatim from the live database, and it contains enough quoting that hand-escaping
 * it into PHP strings is a way to introduce a typo nobody notices until a fresh install.
 *
 * SQLite only. The project runs on SQLite (`DB_CONNECTION=sqlite`); moving to MySQL would
 * mean rewriting baseline.sql, not this class.
 */
return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('schema/baseline.sql');

        if (! is_file($path)) {
            throw new RuntimeException('Missing database/schema/baseline.sql.');
        }

        $sql = (string) file_get_contents($path);

        foreach (preg_split('/;\s*\n/', $sql) ?: [] as $statement) {
            $statement = trim($statement);

            if ($statement === '') {
                continue;
            }

            DB::statement($statement);
        }
    }

    public function down(): void
    {
        // Intentionally empty. The database is the source of truth; restore from
        // storage/app/private/db-dumps/ if this ever has to be undone.
    }
};
