<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BrevoNewsletterService;
use Illuminate\Console\Command;

/**
 * Retries newsletter sign-ups that never reached Brevo.
 *
 * The sign-up endpoint persists locally first and syncs second, so a Brevo outage leaves
 * rows with `brevo_synced = false` rather than losing the address. On shared hosting there
 * is no queue worker to retry them, so this is the retry — on cron, hourly.
 */
class SyncNewsletterCommand extends Command
{
    protected $signature = 'sharkweb:sync-newsletter
                            {--limit=50 : Maximum subscribers to push in one run}';

    protected $description = 'Push pending newsletter subscribers to Brevo';

    public function handle(BrevoNewsletterService $brevo): int
    {
        /*
         * NOT CONFIGURED IS NOT A FAILURE.
         *
         * This runs hourly from cron. Without a Brevo key it used to report "Synced 0, failed
         * N" and exit non-zero, every hour, forever — which on shared hosting means an hour's
         * worth of cron mail about a setting nobody had filled in. It exits 0 and says what to
         * do instead; the pending rows stay pending and drain on the first run after the key
         * is added.
         */
        if (! $brevo->configured()) {
            $this->info('Brevo is not configured. Add the API key and list id in Settings → Newsletter.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $result = $brevo->syncPending($limit);

        if ($result['synced'] === 0 && $result['failed'] === 0) {
            $this->info('No pending subscribers.');

            return self::SUCCESS;
        }

        $this->info("Synced {$result['synced']}, failed {$result['failed']}.");

        // A non-zero exit code makes the failure visible in cron mail rather than silent.
        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
