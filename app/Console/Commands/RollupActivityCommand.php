<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Folds the activity log into daily rollups, then prunes the raw rows (§Maintenance).
 *
 * Two steps, in this order and never reversed: the raw rows are only deleted once their
 * counts are safely aggregated. Raw detail is kept for `sharkweb.activity.retention_days`
 * (default 180) so an incident can be reconstructed in full; the per-day counts land in
 * `activity_daily_rollups` BEFORE any pruning and are kept forever — the growth curve
 * outlives the individual events.
 *
 * Rollups cover the last two days so a 03:45 run catches the tail of "yesterday" and a
 * missed run self-heals the next night without operator attention. The unique
 * (day, log_name, event) index makes re-aggregation an upsert, not a double-count.
 *
 * Deletes are chunked so a large backlog cannot trip cPanel's PHP limits the way one
 * giant DELETE would.
 */
class RollupActivityCommand extends Command
{
    /** How many past days each run re-aggregates. */
    private const CATCH_UP_DAYS = 2;

    protected $signature = 'sharkweb:activity-rollup
                            {--dry-run : Report what would be rolled up and pruned, and change nothing}';

    protected $description = 'Roll the activity log into daily counts, then prune raw rows past retention';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $retentionDays = max(1, (int) config('sharkweb.activity.retention_days', 180));
        $chunk = max(100, (int) config('sharkweb.activity.delete_chunk', 1000));
        $cutoff = Carbon::today()->subDays($retentionDays);

        $rolledUp = 0;

        for ($day = Carbon::today()->subDays(2)->copy(); $day->lt(Carbon::today()); $day->addDay()) {
            $rolledUp += $this->rollUpDay($day, $dryRun);
        }

        if ($dryRun) {
            $prunable = DB::table('activity_log')->where('created_at', '<', $cutoff)->count();
            $this->line("Dry run — would roll up {$rolledUp} activity row(s); {$prunable} raw row(s) are past {$cutoff->toDateString()} and would be pruned.");

            return self::SUCCESS;
        }

        $pruned = $this->prune($cutoff, $chunk);

        $this->info("Rolled up {$rolledUp} activity row(s) into daily counts; pruned {$pruned} raw row(s) older than {$retentionDays} days.");

        return self::SUCCESS;
    }

    /** Aggregate one day's raw rows into the rollup table (upsert, idempotent). */
    private function rollUpDay(Carbon $day, bool $dryRun): int
    {
        $rows = DB::table('activity_log')
            ->selectRaw("COALESCE(log_name, '') as rollup_log, COALESCE(event, '') as rollup_event, COUNT(*) as total")
            ->where('created_at', '>=', $day->copy()->startOfDay())
            ->where('created_at', '<=', $day->copy()->endOfDay())
            ->groupBy('rollup_log', 'rollup_event')
            ->get();

        if ($dryRun || $rows->isEmpty()) {
            return (int) $rows->sum('total');
        }

        foreach ($rows as $row) {
            DB::table('activity_daily_rollups')->updateOrInsert(
                [
                    'day' => $day->toDateString(),
                    'log_name' => $row->rollup_log,
                    'event' => $row->rollup_event,
                ],
                [
                    'count' => (int) $row->total,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        return (int) $rows->sum('total');
    }

    /** Delete past-retention raw rows in bounded chunks. Returns rows removed. */
    private function prune(Carbon $cutoff, int $chunk): int
    {
        $removed = 0;

        do {
            $ids = DB::table('activity_log')
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            DB::table('activity_log')->whereIn('id', $ids)->delete();
            $removed += $ids->count();
        } while ($ids->count() === $chunk);

        return $removed;
    }
}
