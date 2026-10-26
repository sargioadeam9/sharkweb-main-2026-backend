<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\WebhookLog;
use App\Services\Maintenance\JunkCleanerService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class ManageCleaner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-trash';

    protected static ?string $navigationGroup = 'Maintenance';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Junk cleaner';

    protected static ?string $title = 'Junk cleaner';

    protected static string $view = 'filament.pages.manage-cleaner';

    /** The category key currently awaiting a confirmation click. */
    public ?string $confirmingClean = null;

    public function mount(): void {}

    /** Same gate as the backups page — maintenance destroys state, it is for the owner. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /* ------------------------------------------------------------------ *
     * Computed state
     * ------------------------------------------------------------------ */

    /** Non-empty junk categories from the whitelist-only service. */
    public function getCategoriesProperty(): array
    {
        return app(JunkCleanerService::class)->scan();
    }

    /** Never-touch list, for the page's safety rail. */
    public function getNeverTouchProperty(): array
    {
        return JunkCleanerService::NEVER_TOUCH;
    }

    /**
     * Disk usage for the volume hosting the app — on shared cPanel this is the
     * quota the cleaner exists to defend. Returns null when the OS will not say.
     *
     * @return array{total: int, free: int, used: int, percent: int}|null
     */
    public function getDiskUsageProperty(): ?array
    {
        try {
            $total = (int) disk_total_space(base_path());
            $free = (int) disk_free_space(base_path());
        } catch (\Throwable) {
            return null;
        }

        if ($total <= 0) {
            return null;
        }

        return [
            'total' => $total,
            'free' => $free,
            'used' => $total - $free,
            'percent' => (int) round((($total - $free) / $total) * 100),
        ];
    }

    /** Counts behind the DB-prune buttons — so the operator sees what a prune would touch. */
    public function getPruneCountsProperty(): array
    {
        return [
            'webhook_logs' => WebhookLog::count(),
        ];
    }

    /* ------------------------------------------------------------------ *
     * Actions
     * ------------------------------------------------------------------ */

    /** Arm a category's confirm card. Nothing is deleted here. */
    public function askClean(string $key): void
    {
        $this->confirmingClean = $key;
    }

    public function cancelClean(): void
    {
        $this->confirmingClean = null;
    }

    /** Confirmation passed → delete the category, report bytes freed. */
    public function confirmClean(): void
    {
        if ($this->confirmingClean === null) {
            return;
        }

        $key = $this->confirmingClean;
        $category = null;

        foreach ($this->categories as $candidate) {
            if ($candidate['key'] === $key) {
                $category = $candidate;
            }
        }

        if ($category === null) {
            $this->confirmingClean = null;

            return;
        }

        $freed = app(JunkCleanerService::class)->clean($key);

        activity('maintenance')
            ->withProperties(['category' => $key, 'files' => count($category['items']), 'freed_bytes' => $freed])
            ->log('junk_cleaned');

        $this->confirmingClean = null;

        Notification::make()
            ->title('Cleaned: '.$category['label'])
            ->body(number_format((float) $freed / 1024 / 1024, 2).' MB freed')
            ->success()
            ->send();
    }

    /** Manual model:prune for one grow-without-bound table. */
    public function pruneModel(string $model): void
    {
        $class = WebhookLog::class;

        try {
            $before = $class::count();

            Artisan::call('model:prune', ['--model' => [$class]]);
            $pruned = $before - $class::count();

            activity('maintenance')
                ->withProperties(['model' => $class, 'pruned' => $pruned])
                ->log('db_prune');

            Notification::make()
                ->title('Pruned '.$pruned.' row'.($pruned === 1 ? '' : 's').' from '.class_basename($class))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()->title('Prune failed')->body($e->getMessage())->danger()->send();
        }
    }

    /** Roll up activity_log into daily rollups and prune the raw rows past retention. */
    public function runActivityRollup(): void
    {
        try {
            Artisan::call('sharkweb:activity-rollup');

            activity('maintenance')->log('activity_rollup');

            Notification::make()->title('Activity rollup done')->success()->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()->title('Rollup failed')->body($e->getMessage())->danger()->send();
        }
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format($bytes / 1024 / 1024 / 1024, 2).' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
