<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use Arr;
use Ifsnop\Mysqldump\Mysqldump;
use Spatie\DbDumper\DbDumper;

/**
 * A pure-PHP MySQL dumper built on `ifsnop/mysqldump-php`.
 *
 * WHY IT EXISTS. Spatie's MySQL dumper shells out to the `mysqldump` binary; shared cPanel
 * hosting commonly blocks it. Registered against the `mysql` driver in AppServiceProvider
 * via `DbDumperFactory::extend()`, so `backup:run`, rotation, monitoring and notifications
 * all keep working while the dump runs through PHP.
 *
 * One INSERT per row, `add-drop-table` on and a `single-transaction` snapshot: the restore
 * engine replays statements one at a time in resumable chunks, so statements must stay
 * bounded and the snapshot must not block the live site.
 */
class MySqlPureDumper extends DbDumper
{
    public function dumpToFile(string $dumpFile): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            Arr::first(Arr::wrap($this->host)) ?: '127.0.0.1',
            $this->port,
            $this->dbName,
        );

        try {
            $dump = new Mysqldump($dsn, $this->userName, $this->password, [
                'add-drop-table' => true,
                'complete-insert' => true,
                'extended-insert' => false,
                'single-transaction' => true,
                'lock-tables' => false,
                'default-character-set' => Mysqldump::UTF8MB4,
            ]);

            $dump->start($dumpFile);
        } catch (\Throwable $exception) {
            throw new \RuntimeException("The pure-PHP MySQL dump failed: {$exception->getMessage()}", 0, $exception);
        }
    }
}
