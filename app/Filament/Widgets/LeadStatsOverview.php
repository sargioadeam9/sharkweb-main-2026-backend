<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\LeadScore;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The first thing on the dashboard: four figures, no chart.
 *
 * WHY NUMBERS BEFORE CHARTS. The panel used to open straight into two doughnuts, which
 * answer "where did they come from" before answering "how many, and is that more than
 * last week". For a lead-gen business the second question comes first.
 *
 * WHY THESE FOUR. Today is what you act on, seven days is the working week, thirty days is
 * the trend, and hot leads is the only one that says whether the traffic is worth having.
 * Anything else is a fifth box nobody reads.
 *
 * THE SPARKLINE IS THE POINT OF THE SEVEN-DAY STAT. A count of 14 means nothing on its own;
 * 14 climbing over the week does.
 */
class LeadStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    /** Rebuilt on every dashboard load. A cached dashboard is a dashboard nobody trusts. */
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $today = Lead::query()->whereDate('created_at', today())->count();
        $yesterday = Lead::query()->whereDate('created_at', today()->subDay())->count();
        $week = Lead::query()->where('created_at', '>=', now()->subDays(7))->count();
        $month = Lead::query()->where('created_at', '>=', now()->subDays(30))->count();

        $hot = Lead::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->where('lead_score', LeadScore::Hot)
            ->count();

        // One figure per day for the last seven, so the seven-day stat has a shape.
        $perDay = Lead::query()
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($i = 6; $i >= 0; $i--) {
            $series[] = (int) ($perDay[now()->subDays($i)->toDateString()] ?? 0);
        }

        $delta = $today - $yesterday;

        return [
            Stat::make('Leads today', (string) $today)
                ->description($yesterday === 0
                    ? 'none yesterday'
                    : ($delta === 0 ? 'same as yesterday' : ($delta > 0 ? "+{$delta} vs yesterday" : "{$delta} vs yesterday")))
                ->descriptionIcon($delta > 0 ? 'heroicon-m-arrow-trending-up' : ($delta < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($delta > 0 ? 'success' : ($delta < 0 ? 'danger' : 'gray')),

            Stat::make('Last 7 days', (string) $week)
                ->description('daily')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->chart($series)
                ->color('primary'),

            Stat::make('Last 30 days', (string) $month)
                ->description('rolling month')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('gray'),

            Stat::make('Hot leads', (string) $hot)
                ->description('scored hot, last 30 days')
                ->descriptionIcon('heroicon-m-fire')
                ->color($hot > 0 ? 'warning' : 'gray'),
        ];
    }

    /** Nothing to show on a site with no leads yet; the empty state says so better than four zeros. */
    public static function canView(): bool
    {
        return Lead::query()->exists();
    }
}
