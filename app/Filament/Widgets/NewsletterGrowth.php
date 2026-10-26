<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\NewsletterSubscriber;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The list the newsletter is building.
 *
 * WHY IT IS HERE DESPITE THE SMALL NUMBERS. Three subscribers is not a metric, it is a
 * rounding error. But the newsletter is the only channel the agency owns outright — no ad
 * account, no algorithm — so the direction of this number over months is worth more than its
 * current value, and a trend can only be seen if somebody is already looking at it.
 *
 * UNSUBSCRIBED ROWS ARE KEPT BUT EXCLUDED FROM THE TOTAL. They are the denominator for the
 * churn figure, and deleting them would erase the only record that the address ever existed.
 */
class NewsletterGrowth extends BaseWidget
{
    protected ?string $heading = 'Newsletter';

    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $active = NewsletterSubscriber::query()->whereNull('unsubscribed_at');

        $total = (clone $active)->count();
        $week = (clone $active)->where('created_at', '>=', now()->subDays(7))->count();
        $month = (clone $active)->where('created_at', '>=', now()->subDays(30))->count();

        $unsubscribed = NewsletterSubscriber::query()->whereNotNull('unsubscribed_at')->count();

        // Weekly signups for the last eight weeks, so the total has a shape behind it.
        //
        // Bucketed in PHP rather than with SQLite's strftime: `%W` counts weeks from the
        // first Monday and is not the ISO week PHP reports, so the two disagree around new
        // year and the sparkline silently shows a zero for a week that had signups.
        $series = array_fill(0, 8, 0);

        NewsletterSubscriber::query()
            ->whereNull('unsubscribed_at')
            ->where('created_at', '>=', now()->subWeeks(7)->startOfWeek())
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$series): void {
                $weeksAgo = (int) now()->startOfWeek()->diffInWeeks($createdAt->copy()->startOfWeek());

                if ($weeksAgo >= 0 && $weeksAgo < 8) {
                    $series[7 - $weeksAgo]++;
                }
            });

        return [
            Stat::make('Subscribers', (string) $total)
                ->description('active')
                ->descriptionIcon('heroicon-m-envelope')
                ->chart($series)
                ->color('primary'),

            Stat::make('This week', (string) $week)
                ->description('new signups')
                ->descriptionIcon('heroicon-m-plus')
                ->color($week > 0 ? 'success' : 'gray'),

            Stat::make('Last 30 days', (string) $month)
                ->description('new signups')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('gray'),

            Stat::make('Unsubscribed', (string) $unsubscribed)
                ->description($unsubscribed > 0 ? 'no longer receiving' : 'none yet')
                ->descriptionIcon('heroicon-m-minus-circle')
                ->color('gray'),
        ];
    }

    public static function canView(): bool
    {
        return NewsletterSubscriber::query()->exists();
    }
}
