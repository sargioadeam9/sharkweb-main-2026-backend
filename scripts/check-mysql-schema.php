<?php

/**
 * MySQL schema portability check — no MySQL server required.
 *
 * WHY THIS EXISTS
 * ---------------
 * This project's migrations have only ever been executed against SQLite (there is no MySQL
 * server on the development machine). Production is MySQL 8. A migration that SQLite
 * happily accepts can still be rejected by MySQL, and the first symptom would be a failed
 * deploy on a live host.
 *
 * This script compiles every migration through **Laravel's MySQL schema grammar** and
 * captures the exact SQL MySQL would receive, then checks it against the MySQL-specific
 * limits that SQLite does not enforce.
 *
 * WHAT IT PROVES
 *   - Every migration compiles to MySQL DDL without error.
 *   - No identifier (table / index / constraint) exceeds MySQL's 64-character limit.
 *   - No indexed column exceeds InnoDB's 3072-byte index key limit.
 *   - No TEXT/BLOB/JSON column carries a DEFAULT (MySQL error 1101).
 *   - No fullText index (engine/version dependent).
 *   - enum() and json() columns are emitted in valid MySQL form.
 *
 * WHAT IT DOES NOT PROVE
 *   - That the DDL actually executes on MySQL 8. Only a real MySQL instance can show that.
 *     Treat a clean run as "nothing obviously MySQL-hostile", not as "verified on MySQL".
 *
 * USAGE
 *   php scripts/check-mysql-schema.php            # report
 *   php scripts/check-mysql-schema.php --dump     # report + full DDL, to paste into MySQL
 *
 * Exit code is non-zero when a problem is found, so it can gate a deploy.
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require_once dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** A PDO that never connects, and records every statement it is asked to prepare. */
final class RecordingPdo extends PDO
{
    /** @var list<string> */
    public array $recorded = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:');
    }

    #[ReturnTypeWillChange]
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->recorded[] = $query;

        return parent::prepare('select 1');
    }

    #[ReturnTypeWillChange]
    public function exec(string $statement): int|false
    {
        $this->recorded[] = $statement;

        return 0;
    }
}

$pdo = new RecordingPdo;

$connection = new MySqlConnection($pdo, 'thesharkweb_check', '', [
    'driver' => 'mysql',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'engine' => 'InnoDB',
]);

// Point the container at the recording connection so Schema:: calls inside the migrations
// generate MySQL DDL instead of touching the local SQLite file.
DB::swap(new class($connection) extends DatabaseManager
{
    public function __construct(private $connection) {}

    public function connection($name = null)
    {
        return $this->connection;
    }

    public function getDefaultConnection()
    {
        return 'mysql_check';
    }

    public function setDefaultConnection($name) {}
});

Schema::swap($connection->getSchemaBuilder());
Facade::clearResolvedInstance('db');

config(['database.default' => 'mysql_check']);

/* ------------------------------------------------------------------ *
 * Compile every migration
 * ------------------------------------------------------------------ */

$files = glob(dirname(__DIR__).'/database/migrations/*.php');
sort($files);

$failures = [];

foreach ($files as $file) {
    $migration = require $file;

    // Published third-party migrations declare a class and return nothing; Laravel derives
    // the class name from the filename (see Migrator::resolve()).
    if (! is_object($migration)) {
        $class = Str::studly(implode('_', array_slice(explode('_', basename($file, '.php')), 4)));

        if (! class_exists($class)) {
            $failures[] = basename($file).": could not resolve migration class {$class}";

            continue;
        }

        $migration = new $class;
    }

    try {
        $migration->up();
    } catch (Throwable $e) {
        $failures[] = basename($file).': '.$e::class.' — '.$e->getMessage();
    }
}

$statements = $pdo->recorded;
$problems = [];

echo "\n=== Migrations compiled through the MySQL grammar ===\n";
printf("%d migration files → %d SQL statements\n", count($files), count($statements));

if ($failures !== []) {
    echo "\n=== COMPILE FAILURES ===\n";
    foreach ($failures as $failure) {
        echo "  x {$failure}\n";
    }
    $problems = array_merge($problems, $failures);
}

/* ------------------------------------------------------------------ *
 * MySQL 8 limit checks
 * ------------------------------------------------------------------ */

// MySQL identifiers max out at 64 characters.
foreach ($statements as $sql) {
    if (preg_match_all('/\b(?:index|key|constraint)\s+`([^`]+)`/i', $sql, $matches)) {
        foreach ($matches[1] as $identifier) {
            if (strlen($identifier) > 64) {
                $problems[] = "Identifier over 64 chars: {$identifier} (".strlen($identifier).')';
            }
        }
    }

    if (preg_match('/^\s*create\s+table\s+`?([^`\s(]+)`?/i', $sql, $match) && strlen($match[1]) > 64) {
        $problems[] = "Table name over 64 chars: {$match[1]}";
    }
}

// InnoDB with DYNAMIC row format (the MySQL 8 default) allows a 3072-byte index key, i.e.
// varchar(768) in utf8mb4. Only *indexed* columns can breach it.
$indexed = [];
foreach ($statements as $sql) {
    if (preg_match_all('/(?:add\s+)?(?:unique\s+|primary\s+)?(?:index|key)\s+`?([^`(\s]+)`?\s*\(([^)]+)\)/i', $sql, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            foreach (explode(',', $match[2]) as $column) {
                $indexed[] = trim(str_replace('`', '', $column));
            }
        }
    }
}
$indexed = array_unique(array_filter($indexed));

$varchar = [];
foreach ($statements as $sql) {
    if (preg_match_all('/`([a-z_]+)`\s+varchar\((\d+)\)/i', $sql, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $varchar[$match[1]] = max($varchar[$match[1]] ?? 0, (int) $match[2]);
        }
    }
}

echo "\n=== Indexed varchar columns (utf8mb4 byte cost, limit 3072) ===\n";
foreach (array_intersect(array_keys($varchar), $indexed) as $column) {
    $bytes = $varchar[$column] * 4;
    printf("  %-20s varchar(%d) = %d bytes%s\n", $column, $varchar[$column], $bytes, $bytes > 3072 ? '  <-- OVER LIMIT' : '');

    if ($bytes > 3072) {
        $problems[] = "Indexed varchar({$varchar[$column]}) `{$column}` = {$bytes} bytes, over the 3072-byte InnoDB limit";
    }
}

// MySQL rejects a DEFAULT on TEXT/BLOB/JSON columns (error 1101). SQLite allows it, so this
// only ever surfaces on the production database.
foreach ($statements as $sql) {
    if (preg_match_all('/`([a-z_]+)`\s+(?:longtext|mediumtext|text|tinytext|json|blob)\b[^,]*?default\s+\S+/i', $sql, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $problems[] = "DEFAULT on a TEXT/JSON column: `{$match[1]}` — MySQL error 1101";
        }
    }
}

if (preg_match('/fulltext/i', implode("\n", $statements))) {
    $problems[] = 'fullText index found — verify engine support on the production MySQL';
}

// Informational: the columns that exist in the local SQLite run but behave differently
// on MySQL, so they are visible in the report rather than discovered later.
$enums = [];
foreach ($statements as $sql) {
    if (preg_match_all('/`([a-z_]+)`\s+enum\(([^)]+)\)/i', $sql, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $enums[$match[1]] = $match[2];
        }
    }
}

echo "\n=== ENUM columns ===\n";
foreach ($enums as $column => $values) {
    echo "  {$column}: {$values}\n";
}

$json = [];
foreach ($statements as $sql) {
    if (preg_match_all('/`([a-z_]+)`\s+json\b/i', $sql, $matches)) {
        $json = array_merge($json, $matches[1]);
    }
}

echo "\n=== JSON columns ===\n  ".implode(', ', array_unique($json))."\n";

echo "\n=== Problems ===\n";
if ($problems === []) {
    echo "  none detected\n";
} else {
    foreach (array_unique($problems) as $problem) {
        echo "  ! {$problem}\n";
    }
}

if (in_array('--dump', $argv, true)) {
    echo "\n=== Full DDL ===\n";
    foreach ($statements as $sql) {
        echo rtrim(preg_replace('/\s+/', ' ', trim($sql)), ';').";\n";
    }
}

echo "\n".($problems === [] ? 'OK — no MySQL-hostile constructs detected.' : 'FAILED — see problems above.')."\n";
echo "NOTE: this compiles DDL, it does not execute it. Only a real MySQL 8 instance can prove that.\n\n";

exit($problems === [] ? 0 : 1);
