<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Register and inspect the Telegram webhook (§14.8).
 *
 * The webhook is what turns the alert's Approve / Reject buttons into something that works —
 * Telegram has to be told where to post a tap. Doing that by hand means a curl call with a
 * secret in the shell history, so it is a command instead.
 *
 * `--info` is the diagnostic that matters: Telegram stores the last delivery error and keeps
 * retrying, and a webhook that silently stopped being accepted looks exactly like "the
 * buttons do nothing".
 */
class TelegramWebhookCommand extends Command
{
    protected $signature = 'sharkweb:telegram-webhook
                            {--set : Register the webhook with Telegram}
                            {--delete : Remove the webhook}
                            {--info : Show what Telegram currently has}';

    protected $description = 'Register or inspect the Telegram webhook used for one-tap payment approvals';

    public function handle(): int
    {
        $token = (string) config('sharkweb.telegram.bot_token');

        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not set — nothing to talk to.');

            return self::FAILURE;
        }

        if ($this->option('delete')) {
            return $this->delete($token);
        }

        if ($this->option('set')) {
            return $this->set($token);
        }

        return $this->info_($token);
    }

    private function set(string $token): int
    {
        $secret = (string) config('sharkweb.telegram.webhook_secret');

        if ($secret === '') {
            /*
             * Telegram echoes this secret back on every request, and the endpoint refuses
             * requests without it — so it is not optional, and it must be random. Printing
             * one saves a trip to a password generator and, more importantly, stops a
             * guessable value from being typed in by hand.
             */
            $this->error('TELEGRAM_WEBHOOK_SECRET is not set. Add this line to .env and re-run:');
            $this->line('');
            $this->line('    TELEGRAM_WEBHOOK_SECRET='.Str::random(48));
            $this->line('');

            return self::FAILURE;
        }

        $url = $this->webhookUrl();

        try {
            $response = Http::timeout(15)->asJson()->post($this->endpoint($token, 'setWebhook'), [
                'url' => $url,
                'secret_token' => $secret,
                // Only the taps matter; Telegram sends plain messages too, and they are
                // dropped by the controller anyway.
                'allowed_updates' => ['callback_query'],
                'drop_pending_updates' => true,
            ]);
        } catch (Throwable $e) {
            $this->error('Could not reach Telegram: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            $this->error('Telegram refused the webhook: '.$response->body());

            return self::FAILURE;
        }

        $this->info('Webhook registered: '.$url);
        $this->line('Taps from the configured chat will now decide orders.');

        return $this->info_($token);
    }

    private function delete(string $token): int
    {
        try {
            $response = Http::timeout(15)->post($this->endpoint($token, 'deleteWebhook'), [
                'drop_pending_updates' => true,
            ]);
        } catch (Throwable $e) {
            $this->error('Could not reach Telegram: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($response->json('ok') !== true) {
            $this->error('Telegram refused the delete: '.$response->body());

            return self::FAILURE;
        }

        $this->info('Webhook removed. The alert buttons will no longer do anything.');

        return self::SUCCESS;
    }

    private function info_(string $token): int
    {
        try {
            $response = Http::timeout(15)->get($this->endpoint($token, 'getWebhookInfo'));
        } catch (Throwable $e) {
            $this->error('Could not reach Telegram: '.$e->getMessage());

            return self::FAILURE;
        }

        $info = (array) $response->json('result', []);

        $this->table(['Setting', 'Value'], [
            ['Registered URL', (string) ($info['url'] ?? '') ?: '— none —'],
            ['Expected URL', $this->webhookUrl()],
            ['Pending updates', (string) ($info['pending_update_count'] ?? 0)],
            ['Last error', (string) ($info['last_error_message'] ?? '—')],
            ['Secret configured', config('sharkweb.telegram.webhook_secret') ? 'yes' : 'NO — --set will refuse'],
            ['Operator account', $this->operatorStatus()],
        ]);

        return self::SUCCESS;
    }

    /** The account that will be recorded as `verified_by` — or the reason it cannot be. */
    private function operatorStatus(): string
    {
        $email = (string) config('sharkweb.telegram.operator_email');

        if ($email === '') {
            return 'NO — TELEGRAM_OPERATOR_EMAIL is empty, every decision will be refused';
        }

        if (User::query()->where('email', $email)->exists()) {
            return $email;
        }

        /*
         * The usual cause: production was set up with `make:filament-user`, which uses whatever
         * address the owner typed, while this setting defaults to the seeder's address. Naming
         * the accounts that DO exist turns "decisions will be refused" into a one-line fix
         * without opening anything else.
         */
        $candidates = User::query()->orderBy('id')->limit(5)->pluck('email')->all();

        if ($candidates === []) {
            return $email.' — NO USER WITH THIS EMAIL, and no accounts exist at all';
        }

        return $email.' — NO USER WITH THIS EMAIL, decisions will be refused. Existing accounts: '
            .implode(', ', $candidates);
    }

    private function webhookUrl(): string
    {
        $configured = (string) config('sharkweb.telegram.webhook_url');

        return $configured !== '' ? $configured : url('/api/telegram/webhook');
    }

    private function endpoint(string $token, string $method): string
    {
        return "https://api.telegram.org/bot{$token}/{$method}";
    }
}
