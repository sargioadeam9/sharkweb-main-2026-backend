<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\TelegramAlertService;
use Illuminate\Support\Facades\Log;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Throwable;

/**
 * Pushes backup failures to Telegram (§14.14).
 *
 * WHY THIS EXISTS: `spatie/laravel-backup`'s mail notifications go to an inbox nobody
 * watches at 04:00, and on shared cPanel the more likely failure is that the nightly
 * backup stopped running altogether — which produces no email at all, because a job that
 * never ran has no failure to report. `backup:monitor` is the only thing that notices, so
 * its failure has to reach a phone. Telegram is already wired up for lead alerts and costs
 * nothing to reuse.
 *
 * FAILS SOFT. This runs inside a scheduled command on a shared host; a Telegram outage
 * must never turn a warning into a crash. Every path is caught and logged.
 *
 * Only *failure* events are handled. Success notifications are deliberately left to mail
 * (and trimmed in config/backup.php) — three "all good" messages a night trains the owner
 * to filter the sender, which is how a real failure gets missed.
 */
class AlertOnBackupFailure
{
    public function __construct(
        private readonly TelegramAlertService $telegram,
    ) {}

    public function handle(BackupHasFailed|UnhealthyBackupWasFound|CleanupHasFailed $event): void
    {
        try {
            $this->telegram->sendMessage($this->message($event));
        } catch (Throwable $e) {
            // Nothing here is worth breaking a scheduled run over.
            Log::warning('Backup failure alert could not be sent.', ['error' => $e->getMessage()]);
        }
    }

    /** Ordered so the preview line alone says what broke and where. */
    private function message(BackupHasFailed|UnhealthyBackupWasFound|CleanupHasFailed $event): string
    {
        return match (true) {
            $event instanceof UnhealthyBackupWasFound => $this->unhealthyMessage($event),
            $event instanceof CleanupHasFailed => $this->failureMessage('🧹 Backup cleanup failed', $event),
            default => $this->failureMessage('🛑 Backup failed', $event),
        };
    }

    private function failureMessage(string $heading, BackupHasFailed|CleanupHasFailed $event): string
    {
        $lines = ['<b>'.$heading.'</b>', ''];

        if (filled($event->backupName)) {
            $lines[] = '📦 '.$this->escape((string) $event->backupName);
        }

        if (filled($event->diskName)) {
            $lines[] = '💾 Disk: '.$this->escape((string) $event->diskName);
        }

        $lines[] = '';
        // Truncated: a Guzzle exception message can be enormous, and Telegram caps at 4096.
        $lines[] = '<blockquote>'.$this->escape(mb_substr($event->exception->getMessage(), 0, 600)).'</blockquote>';
        $lines[] = '';
        $lines[] = '<i>'.now()->format('j M Y, H:i').'</i>';

        return implode("\n", $lines);
    }

    private function unhealthyMessage(UnhealthyBackupWasFound $event): string
    {
        $lines = [
            '<b>⚠️ Backup health check failed</b>',
            '',
            '📦 '.$this->escape($event->backupName),
            '💾 Disk: '.$this->escape($event->diskName),
            '',
        ];

        foreach ($event->failureMessages as $failure) {
            $lines[] = '• <b>'.$this->escape((string) ($failure['check'] ?? 'check')).'</b>: '
                .$this->escape((string) ($failure['message'] ?? ''));
        }

        $lines[] = '';
        $lines[] = '<i>'.now()->format('j M Y, H:i').'</i>';

        return implode("\n", $lines);
    }

    /** Telegram HTML mode only requires these three. */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
