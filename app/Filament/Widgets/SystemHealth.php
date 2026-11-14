<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * The three failures that are invisible until somebody goes looking.
 *
 * WHY IT IS ON THE DASHBOARD AT ALL. A failed job, a rejected ISR webhook and a stale cache
 * all look identical from the outside: the site keeps serving, the panel keeps working, and
 * the only symptom is that a page is out of date or an email never arrived. Nobody opens a
 * maintenance page on a good day, which is exactly why the bad day goes unnoticed.
 *
 * IT IS THE LAST WIDGET ON PURPOSE. These are zero almost every day, and a row of green
 * zeroes at the top of a dashboard trains people to stop reading the top of the dashboard.
 *
 * A WEBHOOK COUNTS AS FAILED when it never got a response or got a 4xx/5xx. The table has no
 * `success` column — it records `response_status`, and null means the request never completed.
 */
class SystemHealth extends BaseWidget
{
    protected ?string $heading = 'System health';

    protected static ?int $sort = 11;

    protected function getStats(): array
    {
        $failedJobs = DB::table('failed_jobs')->count();

        $webhookErrors = DB::table('webhook_logs')
            ->where('created_at', '>=', now()->subDays(7))
            ->where(fn ($q) => $q->whereNull('response_status')->orWhere('response_status', '>=', 400))
            ->count();

        $lastDelivery = DB::table('webhook_logs')
            ->whereNotNull('response_status')
            ->where('response_status', '<', 400)
            ->max('created_at');

        return [
            Stat::make('Failed jobs', (string) $failedJobs)
                ->description($failedJobs > 0 ? 'queued work that gave up' : 'nothing has failed')
                ->descriptionIcon($failedJobs > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($failedJobs > 0 ? 'danger' : 'success'),

            Stat::make('Webhook errors', (string) $webhookErrors)
                ->description('last 7 days')
                ->descriptionIcon($webhookErrors > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($webhookErrors > 0 ? 'danger' : 'success'),

            Stat::make('Last content push', $lastDelivery ? \Illuminate\Support\Carbon::parse($lastDelivery)->diffForHumans() : 'never')
                ->description($lastDelivery ? 'frontend accepted an update' : 'no successful push recorded')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('gray'),
        ];
    }

    /**
     * Only shown once the site has taken a lead, so a brand-new install is not greeted with a
     * health panel before there is anything to be healthy about.
     */
    public static function canView(): bool
    {
        return Lead::query()->exists();
    }
}
