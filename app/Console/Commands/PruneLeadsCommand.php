<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Content\CaseStudy;
use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Enforce the 24-month privacy policy retention promise for CRM enquiries.
 *
 * "Enquiries: 24 months after the last contact, then deleted from our CRM by an automated daily command."
 *
 * SAFETY RAILS:
 *  - Only leads with NO contact in the last `--days` (default 730 = 24 months) are touched.
 *  - Contact is measured by the latest of `created_at`, `updated_at`,
 *    `qualified_invite_sent_at`, and `booking_reminder_sent_at`.
 *  - Converted clients are NEVER deleted: any lead whose internal notes flag client status
 *    or whose record matches an active case study client is preserved.
 *  - LIMIT EXCEEDED GUARD: If candidate count exceeds `--limit` in live mode, the command refuses
 *    to delete and fails safely to prevent accidental bulk data loss.
 *  - `--dry-run` reports candidate records without modifying the database.
 */
class PruneLeadsCommand extends Command
{
    protected $signature = 'leads:prune
                            {--days=730 : How many days past last contact before an enquiry is pruned}
                            {--limit=100 : Maximum leads allowed to be pruned in one run}
                            {--dry-run : Report candidate leads without deleting}';

    protected $description = 'Prune enquiries past the 24-month retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $cutoff = Carbon::now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $baseQuery = Lead::withTrashed()
            ->where('created_at', '<=', $cutoff)
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('updated_at')
                    ->orWhere('updated_at', '<=', $cutoff);
            })
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('qualified_invite_sent_at')
                    ->orWhere('qualified_invite_sent_at', '<=', $cutoff);
            })
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('booking_reminder_sent_at')
                    ->orWhere('booking_reminder_sent_at', '<=', $cutoff);
            });

        $candidateCount = $baseQuery->count();

        if ($candidateCount === 0) {
            $this->info('No enquiries are due for retention pruning.');

            return self::SUCCESS;
        }

        // Safety rail: refuse to delete if candidate count exceeds the limit in live mode
        if (! $dryRun && $candidateCount > $limit) {
            $this->line("Safety abort: {$candidateCount} candidate enquiries exceed the single-run limit of {$limit}. Refusing to delete in live mode. Inspect candidates with --dry-run first or explicitly increase --limit.");

            return self::FAILURE;
        }

        $candidates = (clone $baseQuery)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $clientNames = $this->activeClientNames();
        $pruned = 0;
        $skipped = 0;

        foreach ($candidates as $lead) {
            if ($this->isConvertedClient($lead, $clientNames)) {
                $this->line("skipping converted client: #{$lead->id} ({$lead->displayName()})");
                $skipped++;
                continue;
            }

            $lastContact = $this->latestContact($lead);

            if ($dryRun) {
                $this->line("would prune: #{$lead->id} {$lead->email} (last contact {$lastContact?->diffForHumans()})");
                $pruned++;
                continue;
            }

            $lead->forceDelete();
            $pruned++;
        }

        $verb = $dryRun ? 'Would prune' : 'Pruned';
        $this->info("{$verb} {$pruned} enquiry records. Skipped {$skipped} client records.");

        return self::SUCCESS;
    }

    /**
     * Determine the latest timestamp of any interaction with this lead.
     */
    private function latestContact(Lead $lead): ?Carbon
    {
        $timestamps = array_filter([
            $lead->booking_reminder_sent_at,
            $lead->qualified_invite_sent_at,
            $lead->updated_at,
            $lead->created_at,
        ]);

        if (empty($timestamps)) {
            return null;
        }

        usort($timestamps, fn (Carbon $a, Carbon $b) => $b->getTimestamp() <=> $a->getTimestamp());

        return $timestamps[0];
    }

    /**
     * Check if this lead became an active client or has a contract note.
     *
     * @param  array<string>  $caseStudyClients
     */
    private function isConvertedClient(Lead $lead, array $caseStudyClients): bool
    {
        $notes = mb_strtolower((string) $lead->internal_notes);

        if ($notes !== '' && preg_match('/\b(client|contract|retained|signed|active project)\b/u', $notes)) {
            return true;
        }

        $name = mb_strtolower(trim((string) $lead->name));
        $email = mb_strtolower(trim((string) $lead->email));

        foreach ($caseStudyClients as $client) {
            if ($client !== '' && (str_contains($name, $client) || str_contains($notes, $client) || str_contains($email, $client))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    private function activeClientNames(): array
    {
        try {
            return CaseStudy::active()
                ->pluck('client')
                ->filter()
                ->map(fn ($c): string => mb_strtolower(trim((string) $c)))
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}