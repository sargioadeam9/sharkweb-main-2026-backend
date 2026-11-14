<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\RestoreRun;
use Illuminate\Support\Facades\DB;
use PDO;
use ZipArchive;

/**
 * Replays a backup's database dump into the live database, in resumable chunks (§Maintenance).
 *
 * WHY CHUNKED. Shared cPanel hosting has no queue worker and kills a PHP process on
 * max_execution_time, so a two-year database cannot be replayed in one request. A run is
 * split into statement chunks executed on successive HTTP requests, driven by the
 * maintenance page's polling; the run row carries a byte offset into the extracted SQL
 * file, so a request that dies mid-chunk leaves the next request a precise place to
 * continue from. No progress is ever thrown away.
 *
 * STATEMENTS ARE SPLIT BY A QUOTE-AWARE SCANNER, NOT BY LINE. Post and portfolio bodies
 * are Markdown whose string literals legitimately contain newlines, so "one statement per
 * line" is a fiction — the splitter walks characters and only treats a `;` as a terminator
 * when it sits OUTSIDE a single-quoted string. The in-string state and any unfinished
 * statement are themselves persisted on the run row, so a resumed request continues
 * mid-statement exactly where the last one died.
 *
 * The dump's own transaction markers (BEGIN/COMMIT/PRAGMA) are stripped; every CHUNK is
 * wrapped in one real transaction instead, so a chunk either lands whole or not at all.
 *
 * Only the DATABASE is restored. Media files are untouched — a file restore would
 * overwrite images uploaded since the backup, and the zip's media set is exactly what is
 * already on disk unless it was deleted deliberately.
 */
class RestoreService
{
    /** Statements replayed per chunk. Tuned so one request stays inside cPanel's time limits. */
    private const CHUNK = 200;

    /** Exact statements the dump emits that must never be replayed as-is. */
    private const SKIP = [
        'PRAGMA foreign_keys=OFF;',
        'BEGIN TRANSACTION;',
        'START TRANSACTION;',
        'COMMIT;',
    ];

    /**
     * Prepare a run from a backup zip: extract the dump, count its statements, record
     * the safety backup. The caller (the Filament page) takes the safety backup first
     * and passes its name — the order matters, the safety net must capture the CURRENT
     * state, and doing it here would make failure mid-preparation ambiguous.
     */
    public function prepare(string $zipPath, string $safetyBackup): RestoreRun
    {
        if (RestoreRun::query()->where('status', RestoreRun::STATUS_RUNNING)->exists()
            || RestoreRun::query()->where('status', RestoreRun::STATUS_PENDING)->exists()) {
            throw new \RuntimeException('A restore is already in progress. Wait for it to finish.');
        }

        $sqlFile = $this->extractSql($zipPath);
        $total = $this->countStatements($sqlFile);

        return RestoreRun::query()->create([
            'backup_path' => $zipPath,
            'sql_file' => $sqlFile,
            'status' => RestoreRun::STATUS_PENDING,
            'total_statements' => $total,
            'processed_statements' => 0,
            'processed_bytes' => 0,
            'pending' => '',
            'in_string' => false,
            'safety_backup' => $safetyBackup,
        ]);
    }

    /**
     * Replay the next chunk of statements. Callable repeatedly by the page's polling;
     * each call advances the run by up to self::CHUNK statements and returns.
     */
    public function advance(RestoreRun $run): RestoreRun
    {
        if (! $run->isActive() || $run->sql_file === null || ! is_file($run->sql_file)) {
            return $run;
        }

        if ($run->status === RestoreRun::STATUS_PENDING) {
            $run->update(['status' => RestoreRun::STATUS_RUNNING, 'started_at' => now()]);
        }

        $handle = fopen((string) $run->sql_file, 'r');

        if ($handle === false) {
            return $this->fail($run, 'The extracted SQL file could not be reopened.');
        }

        fseek($handle, (int) $run->processed_bytes);

        $pdo = DB::connection()->getPdo();

        try {
            // Schema replay drops and recreates tables. Foreign keys must be off for the
            // whole run; it is a connection-level setting and a no-op inside a transaction.
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $pdo->exec('PRAGMA foreign_keys=OFF');
            }

            $pdo->beginTransaction();

            $result = $this->replay($handle, $pdo, (bool) $run->in_string, (string) $run->pending);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            fclose($handle);

            return $this->fail($run, $e->getMessage());
        }

        $eof = $result['eof'];

        // Trim: whitespace left over after the final `;` (trailing newlines) is not an
        // unfinished statement.
        if ($eof && trim($result['buffer']) === '') {
            $run->update([
                'status' => RestoreRun::STATUS_DONE,
                'processed_statements' => $run->total_statements,
                'finished_at' => now(),
            ]);

            // The extracted dump has served its purpose and is pure disk waste now.
            @unlink((string) $run->sql_file);

            activity('maintenance')->log('restore_completed');

            return $run->refresh();
        }

        $run->update([
            'processed_statements' => $run->processed_statements + $result['executed'],
            // The scanner's byte position, NOT ftell(): fread pulls 64KB blocks but the
            // chunk may stop mid-block, and resuming from ftell would silently skip the
            // unconsumed tail of the block.
            'processed_bytes' => $result['offset'],
            'pending' => $result['buffer'],
            'in_string' => $result['inString'],
            'status' => RestoreRun::STATUS_RUNNING,
        ]);

        fclose($handle);

        return $run->refresh();
    }

    /**
     * The quote-aware scanner: consumes blocks from $handle, executes complete
     * statements until self::CHUNK is reached or the file ends.
     *
     * A `'` toggles string state — doubled (`''`) inside a string is SQL's escaped
     * quote and consumed as one. `;` only terminates outside a string. A statement
     * left unfinished when the chunk cap hits is returned in `buffer` and re-parsed
     * by the next request; `inString` travels with it.
     *
     * @return array{executed: int, eof: bool, buffer: string, inString: bool, offset: int}
     */
    private function replay($handle, PDO $pdo, bool $inString, string $buffer): array
    {
        $executed = 0;
        $eof = false;
        $offset = 0;

        while ($executed < self::CHUNK) {
            $blockStart = (int) ftell($handle);
            $block = fread($handle, 65536);

            if ($block === false || $block === '') {
                $offset = (int) ftell($handle);
                $eof = true;
                break;
            }

            $length = strlen($block);
            $consumed = null;
            $chunkFull = false;

            for ($i = 0; $i < $length; $i++) {
                $char = $block[$i];
                $buffer .= $char;

                if ($char === "'") {
                    if ($inString) {
                        // '' inside a string is the escaped quote, not its end.
                        if ($i + 1 < $length && $block[$i + 1] === "'") {
                            $buffer .= "'";
                            $i++;
                        } else {
                            $inString = false;
                        }
                    } else {
                        $inString = true;
                    }

                    continue;
                }

                if ($char === ';' && ! $inString) {
                    $statement = trim($buffer);
                    $buffer = '';

                    if ($statement === '' || in_array($statement, self::SKIP, true)
                        || self::isStateTableStatement($statement)) {
                        continue;
                    }

                    $pdo->exec($statement);
                    $executed++;

                    if ($executed >= self::CHUNK) {
                        $consumed = $i + 1;
                        break;
                    }
                }
            }

            if ($consumed === null) {
                $consumed = $length;

                // A quote as the block's final byte may be the first half of a '' pair
                // that continues in the next block — unreadable without lookahead. Rewind
                // it: the next read decides with both bytes in view. inString true here
                // always means that final byte was a string-entering quote; a closing
                // one would have left inString false.
                if ($inString && $length > 0 && $block[$length - 1] === "'") {
                    fseek($handle, $blockStart + $length - 1);
                    $buffer = substr($buffer, 0, -1);
                    $consumed = $length - 1;
                    $inString = false;
                }

                if (feof($handle)) {
                    $eof = true;
                }
            }

            $offset = $blockStart + $consumed;

            if ($consumed < $length || $eof) {
                break;
            }
        }

        // An unterminated statement at EOF means a truncated dump — refuse to pretend
        // it restored. (Whitespace left over after the final `;` is not a statement.)
        if ($eof && trim($buffer) !== '') {
            throw new \RuntimeException('The dump ended mid-statement; the archive appears truncated.');
        }

        return ['executed' => $executed, 'eof' => $eof, 'buffer' => $buffer, 'inString' => $inString, 'offset' => $offset];
    }

    /** Extract the db-dumps/*.sql from the backup zip into backup-temp. Returns the path. */
    private function extractSql(string $zipPath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("The backup archive [{$zipPath}] could not be opened.");
        }

        try {
            $found = null;
            $rawName = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);

                // Windows writes zip entries with backslash separators; normalise before
                // matching or the dump inside an archive made on a dev box is invisible.
                $name = str_replace('\\', '/', $entry);

                if (str_starts_with($name, 'db-dumps/') && str_ends_with($name, '.sql')) {
                    $found = $name;
                    // Extraction must use the entry's ORIGINAL name — the archive indexes
                    // by the exact string it was written with.
                    $rawName = $entry;
                    break;
                }
            }

            if ($found === null) {
                throw new \RuntimeException('The backup contains no database dump. Only full backups with a db-dumps/ folder can be restored.');
            }

            $tempDir = storage_path('app/backup-temp');

            if (! is_dir($tempDir) && ! mkdir($tempDir, 0775, true) && ! is_dir($tempDir)) {
                throw new \RuntimeException("Could not create [{$tempDir}] for the extracted dump.");
            }

            $target = $tempDir.'/restore-'.uniqid().'.sql';

            $contents = $zip->getFromName($rawName ?? $found);

            if ($contents === false) {
                throw new \RuntimeException("Could not read [{$found}] from the backup archive.");
            }

            if (file_put_contents($target, $contents) === false) {
                throw new \RuntimeException("Could not extract [{$found}] from the backup.");
            }

            return $target;
        } finally {
            $zip->close();
        }
    }

    /** Parsed, executable statements in the extracted file — the chunk engine's total. */
    private function countStatements(string $sqlFile): int
    {
        $handle = fopen($sqlFile, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Could not read [{$sqlFile}] to count its statements.");
        }

        $count = 0;
        $inString = false;
        $buffer = '';

        while (($block = fread($handle, 65536)) !== false && $block !== '') {
            $length = strlen($block);

            for ($i = 0; $i < $length; $i++) {
                $char = $block[$i];
                $buffer .= $char;

                if ($char === "'") {
                    if ($inString) {
                        if ($i + 1 < $length && $block[$i + 1] === "'") {
                            $buffer .= "'";
                            $i++;
                        } else {
                            $inString = false;
                        }
                    } else {
                        $inString = true;
                    }

                    continue;
                }

                if ($char === ';' && ! $inString) {
                    $statement = trim($buffer);
                    $buffer = '';

                    if ($statement === '' || in_array($statement, self::SKIP, true)
                        || self::isStateTableStatement($statement)) {
                        continue;
                    }

                    $count++;
                }
            }
        }

        fclose($handle);

        return $count;
    }

    /**
     * Statements touching the restore-state table must NOT be replayed.
     *
     * The dump is a snapshot of the database, and the run row that is driving this
     * restore lives in that table. Replaying its DROP/CREATE/INSERT from the backup
     * would delete the live run out from under the replay engine — the operation would
     * cut off its own pulse mid-run. The table is archived inside the zip (a backup
     * should be complete) but the replay skips every statement that touches it, so the
     * run's own state survives the restore.
     */
    private static function isStateTableStatement(string $statement): bool
    {
        return preg_match(
            '/^(DROP\s+(?:TABLE|INDEX|TRIGGER|VIEW)\s+IF\s+EXISTS\s+"?sharkweb_restore_runs"?\s*;?|CREATE\s+(?:TABLE|INDEX|TRIGGER|VIEW)\s+"?sharkweb_restore_runs"?|INSERT\s+INTO\s+"?sharkweb_restore_runs"?)/i',
            $statement,
        ) === 1;
    }

    private function fail(RestoreRun $run, string $message): RestoreRun
    {
        $run->update([
            'status' => RestoreRun::STATUS_FAILED,
            'error' => $message,
            'finished_at' => now(),
        ]);

        activity('maintenance')->withProperties(['error' => $message])->log('restore_failed');

        return $run;
    }
}
