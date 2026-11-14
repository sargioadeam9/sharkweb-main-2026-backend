<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use PDO;
use Spatie\DbDumper\DbDumper;

/**
 * A pure-PHP SQLite dumper that emits plain SQL text.
 *
 * WHY IT EXISTS. Spatie's own SQLite dumper shells out to the `sqlite3` binary, which this
 * machine does not have and which shared cPanel hosting commonly blocks. This class is
 * registered against the `sqlite` driver in AppServiceProvider via
 * `DbDumperFactory::extend()`, so `backup:run` keeps all of spatie's machinery (zip,
 * rotation, monitoring, notifications) while the dump itself runs through PHP.
 *
 * THE OUTPUT IS ONE INSERT PER ROW. The restore engine replays statements one at a time in
 * resumable chunks, and a single multi-row INSERT for a grown table could not be streamed
 * back a piece at a time. Zip compression makes the size difference a rounding error.
 *
 * Row reads are windowed by `rowid` so memory stays flat on a table of any size — a table
 * that reaches millions of rows over two years must dump with the same footprint as today.
 * The cursor is the LAST SEEN ROWID, not a row count: rowids are sparse after deletes, and
 * a count-based cursor would silently skip rows sitting behind a gap.
 */
class SqlitePureDumper extends DbDumper
{
    private const CHUNK = 500;

    /**
     * spatie builds the dump's FILENAME from the db name, and for SQLite that name is the
     * full database path — `D:\...\database.sqlite` on Windows, where the drive colon makes
     * the filename illegal and `backup:run` dies inside a mkdir. The dump still reads the
     * full path from the raw property; only the display name is reduced to the basename.
     */
    public function getDbName(): string
    {
        return basename($this->dbName);
    }

    public function dumpToFile(string $dumpFile): void
    {
        $pdo = new PDO('sqlite:'.$this->dbName, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // foreign_keys is a no-op inside a transaction, so it comes first. The read
        // transaction then pins ONE snapshot for schema and data together while the site
        // keeps writing; WAL lets readers work alongside writers, so nothing is blocked.
        $pdo->exec('PRAGMA foreign_keys=OFF');
        $pdo->exec('BEGIN');

        $handle = fopen($dumpFile, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Could not open [{$dumpFile}] for the SQLite dump.");
        }

        $write = fn (string $line) => fwrite($handle, $line."\n");

        $write("PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n");

        // Every object gets a DROP before any CREATE, so replaying the dump rebuilds the
        // schema in place without a separate "clear it first" step. Tables are created
        // before views/indexes/triggers because a trigger may reference a table that
        // happens to sort later alphabetically.
        $objects = $pdo->query(
            "SELECT type, name, sql FROM sqlite_master
             WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%'
             ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END, name"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($objects as $object) {
            $write('DROP '.$object['type'].' IF EXISTS '.self::ident((string) $object['name']).';');
        }

        foreach ($objects as $object) {
            $write($object['sql'].';');
        }

        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->writeRows($pdo, $write, (string) $table);
        }

        $write('COMMIT;');

        $pdo->exec('COMMIT');

        if (fclose($handle) === false) {
            throw new \RuntimeException("Could not finish writing the SQLite dump to [{$dumpFile}].");
        }
    }

    /** One INSERT per row, walked by rowid so memory stays flat on a table of any size. */
    private function writeRows(PDO $pdo, callable $write, string $table): void
    {
        $quoted = self::ident($table);

        $columns = array_map(
            fn (array $column) => self::ident((string) $column['name']),
            $pdo->query('PRAGMA table_info('.$quoted.')')->fetchAll(PDO::FETCH_ASSOC),
        );

        if ($columns === []) {
            return;
        }

        $columnList = implode(', ', $columns);

        // WITHOUT ROWID tables have no rowid to window over; none exist in this schema, but
        // a dump must not assume that forever, so there is an OFFSET fallback.
        $hasRowid = $this->hasRowid($pdo, $quoted);

        $cursor = 0;

        while (true) {
            if ($hasRowid) {
                $statement = $pdo->prepare(
                    'SELECT rowid, * FROM '.$quoted.' WHERE rowid > ? ORDER BY rowid LIMIT '.self::CHUNK
                );
                $statement->execute([$cursor]);
            } else {
                $statement = $pdo->query(
                    'SELECT * FROM '.$quoted.' LIMIT '.self::CHUNK.' OFFSET '.$cursor
                );
            }

            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if ($hasRowid) {
                    // The rowid is transport for the window walk, not part of the table's
                    // data — it must not reach the INSERT.
                    unset($row['rowid']);
                }

                $write('INSERT INTO '.$quoted.' ('.$columnList.') VALUES ('
                    .implode(', ', array_map(self::literal(...), $row)).');');
            }

            if (count($rows) < self::CHUNK) {
                break;
            }

            if ($hasRowid) {
                $cursor = (int) $rows[count($rows) - 1]['rowid'];
            } else {
                $cursor += self::CHUNK;
            }
        }
    }

    private function hasRowid(PDO $pdo, string $quotedTable): bool
    {
        try {
            $pdo->query('SELECT rowid FROM '.$quotedTable.' LIMIT 1');

            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /** One SQL literal from one PHP value, in SQLite's dialect. */
    public static function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            // Blobs arrive as streams; a hex literal survives any encoding round-trip.
            is_resource($value) => "X'".bin2hex((string) stream_get_contents($value))."'",
            default => "'".str_replace("'", "''", (string) $value)."'",
        };
    }

    /** Double-quoted SQL identifier. */
    private static function ident(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
}
