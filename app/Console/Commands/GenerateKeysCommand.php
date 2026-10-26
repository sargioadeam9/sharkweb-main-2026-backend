<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Generates the server-to-server secrets the backend and the Next.js frontend share.
 *
 * Referenced from `.env.example`. Run this once per environment and paste the values
 * into BOTH apps' environment config — they must match exactly or every proxied request
 * will be rejected with 401.
 *
 * Values are printed rather than written to .env on purpose: this command is also run
 * against production, where editing the live .env from a shell is exactly the kind of
 * thing that takes a site down.
 */
class GenerateKeysCommand extends Command
{
    protected $signature = 'sharkweb:generate-keys
                            {--force : Regenerate even if values already exist in the environment}';

    protected $description = 'Generate the LARAVEL_API_KEY, PREVIEW_SECRET, REVALIDATE_SECRET, LEAD_WEBHOOK_SECRET and INDEXNOW_KEY values';

    public function handle(): int
    {
        $existing = [
            'LARAVEL_API_KEY' => config('sharkweb.api_key'),
            'PREVIEW_SECRET' => config('sharkweb.preview_secret'),
            'REVALIDATE_SECRET' => config('sharkweb.isr.secret'),
            'LEAD_WEBHOOK_SECRET' => config('sharkweb.leads.webhook_secret'),
            'INDEXNOW_KEY' => config('sharkweb.indexnow.key'),
        ];

        $alreadySet = array_filter($existing);

        if ($alreadySet !== [] && ! $this->option('force')) {
            $this->warn('These values are already configured:');
            $this->line('  '.implode(', ', array_keys($alreadySet)));
            $this->newLine();
            $this->line('Re-run with --force to generate a fresh set anyway.');
            $this->line('Rotating LARAVEL_API_KEY will break the frontend until it is updated too.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Add these to the backend .env AND the matching Next.js environment:');
        $this->newLine();

        $rows = [
            ['LARAVEL_API_KEY', Str::random(64)],
            ['PREVIEW_SECRET', Str::random(48)],
            ['REVALIDATE_SECRET', Str::random(48)],
            ['LEAD_WEBHOOK_SECRET', Str::random(48)],
            ['INDEXNOW_KEY', 'sharkweb_indexnow_'.Str::lower(Str::random(16))],
        ];

        foreach ($rows as [$key, $value]) {
            $this->line("  <fg=green>{$key}</>={$value}");
        }

        $this->newLine();
        $this->comment('The IndexNow key must also be served as a plain-text file at');
        $this->comment('https://thesharkweb.com/{INDEXNOW_KEY}.txt, containing the key itself.');
        $this->newLine();
        $this->comment('After editing .env run: php artisan config:clear');
        $this->newLine();

        return self::SUCCESS;
    }
}
