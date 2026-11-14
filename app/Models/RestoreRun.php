<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One attempt to replay a backup's database dump into the live database (§Maintenance).
 *
 * This row is the restore's whole brain: the byte offset inside the extracted SQL file is
 * the cursor, so any request can continue exactly where the previous one stopped. See
 * App\Services\Maintenance\RestoreService for the replay engine.
 */
class RestoreRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $table = 'sharkweb_restore_runs';

    protected $fillable = [
        'backup_path',
        'sql_file',
        'status',
        'total_statements',
        'processed_statements',
        'processed_bytes',
        // The scanner's carry-over: the statement that was in progress when the last
        // chunk ended, and whether the scanner was inside a string literal at that
        // moment. Together with processed_bytes they make a mid-statement resume exact.
        'pending',
        'in_string',
        'safety_backup',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'in_string' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** Whether a run is between "started" and "finished" — it owns the restore machinery. */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true);
    }

    /** 0–100, for the progress bar. */
    public function percentComplete(): int
    {
        if ($this->total_statements === 0) {
            return $this->status === self::STATUS_DONE ? 100 : 0;
        }

        return (int) min(100, floor(($this->processed_statements / $this->total_statements) * 100));
    }
}
