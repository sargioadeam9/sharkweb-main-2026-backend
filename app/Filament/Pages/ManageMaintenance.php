<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\RestoreRun;
use App\Services\Maintenance\RestoreService;
use Carbon\Carbon;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Spatie\Backup\BackupDestination\BackupDestination;

class ManageMaintenance extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Maintenance';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Backups & restore';

    protected static ?string $title = 'Backups & restore';

    protected static string $view = 'filament.pages.manage-maintenance';

    /** The filename currently awaiting a typed RESTORE confirmation. */
    public ?string $confirmingRestore = null;

    /** The user's typed confirmation word. */
    public string $restoreConfirmation = '';

    public function mount(): void {}

    /** Maintenance pages destroy or overwrite state; they are for the owner's own account. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /* ------------------------------------------------------------------ *
     * Backups list
     * ------------------------------------------------------------------ */

    /**
     * Every backup on the configured destination disk, newest first.
     *
     * Read through spatie's BackupDestination (not raw globbing) so the page follows the
     * same config as backup:run — which means the day the destination moves to S3 or
     * Backblaze, this list keeps working without a line changed.
     */
    public function getBackupsProperty(): array
    {
        $destination = BackupDestination::create(
            config('backup.backup.destination.disks')[0] ?? 'local',
            config('backup.backup.name'),
        );

        return collect($destination->backups())
            ->map(function ($backup) {
                // Download and restore read the local filesystem directly; a remote
                // destination (S3/B2) lists fine here but those actions stay disabled
                // until the destination moves and they get stream-based versions.
                $relative = (string) $backup->path();
                $absolute = null;

                try {
                    $absolute = $backup->disk()->path($relative);
                } catch (\Throwable) {
                    // Not a local adapter — list it, but nothing is restorable.
                }

                return [
                    'path' => $absolute ?? $relative,
                    'name' => basename($relative),
                    'size' => (int) $backup->sizeInBytes(),
                    'date' => $backup->date(),
                    'restorable' => $absolute !== null && is_file($absolute),
                ];
            })
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    /** The run the polling engine is currently advancing, if any. */
    public function getActiveRunProperty(): ?RestoreRun
    {
        return RestoreRun::query()
            ->whereIn('status', [RestoreRun::STATUS_PENDING, RestoreRun::STATUS_RUNNING])
            ->orderByDesc('id')
            ->first();
    }

    /** The most recently finished run — what the operator sees after a restore. */
    public function getLastRunProperty(): ?RestoreRun
    {
        return RestoreRun::query()->orderByDesc('id')->first();
    }

    /* ------------------------------------------------------------------ *
     * Actions
     * ------------------------------------------------------------------ */

    public function runBackup(): void
    {
        try {
            Artisan::call('backup:run');

            activity('maintenance')->log('backup_run');

            Notification::make()->title('Backup completed')->success()->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Backup failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function downloadBackup(string $path): mixed
    {
        if (! is_file($path)) {
            Notification::make()->title('The backup file is no longer on disk.')->danger()->send();

            return null;
        }

        return response()->download($path);
    }

    /**
     * Arm the typed-confirmation card. Nothing destructive happens here — the actual
     * restore starts only when the confirmation word matches in confirmRestore().
     */
    public function askRestore(string $path): void
    {
        $this->confirmingRestore = $path;
        $this->restoreConfirmation = '';
    }

    public function cancelRestore(): void
    {
        $this->confirmingRestore = null;
        $this->restoreConfirmation = '';
    }

    /**
     * Typed RESTORE passed → safety backup, then prepare the run. The replay itself is
     * driven by polling calling advanceRestore() until the run is done.
     */
    public function confirmRestore(): void
    {
        if ($this->confirmingRestore === null) {
            return;
        }

        if ($this->restoreConfirmation !== 'RESTORE') {
            Notification::make()->title('Type RESTORE to confirm.')->danger()->send();

            return;
        }

        $zipPath = $this->confirmingRestore;

        // SAFETY BACKUP FIRST, and it must succeed: its whole purpose is undoing what
        // we are about to do. A failed safety backup aborts the restore — continuing
        // with no way back would be a choice the owner never got to make.
        try {
            Artisan::call('backup:run');
            $safetyBackup = $this->lastBackupName();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('The pre-restore safety backup failed, so the restore was not started.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        try {
            $run = app(RestoreService::class)->prepare($zipPath, $safetyBackup ?? 'unknown');

            activity('maintenance')
                ->withProperties(['backup' => basename($zipPath), 'statements' => $run->total_statements])
                ->log('restore_started');

            $this->confirmingRestore = null;
            $this->restoreConfirmation = '';

            Notification::make()
                ->title('Restore started')
                ->body('Do not close this page until the progress bar completes.')
                ->warning()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Restore could not start')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * The polling action: advance the active run by one chunk. Called every second by
     * the blade view while a run is active — never from anywhere else.
     */
    public function advanceRestore(): void
    {
        $run = $this->activeRun;

        if ($run === null) {
            return;
        }

        try {
            app(RestoreService::class)->advance($run);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The safety backup's own filename, for the done-notification. */
    private function lastBackupName(): ?string
    {
        $backups = $this->backups;

        return $backups === [] ? null : basename((string) $backups[0]['path']);
    }

    /** Age in human terms, for the list. */
    public function formatAge(Carbon $date): string
    {
        return $date->locale('en')->diffForHumans();
    }
}
