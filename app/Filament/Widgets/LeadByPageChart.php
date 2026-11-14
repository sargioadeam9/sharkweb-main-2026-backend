<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

/**
 * Which page is converting (§14.1).
 *
 * Read alongside LeadSourceChart: a channel with leads but a weak landing page is a
 * message-match problem, while a page with traffic and no leads is a page problem. Neither
 * chart answers that alone.
 *
 * A horizontal bar chart, because page names are long and a vertical axis would either
 * truncate them or force the chart to rotate labels.
 */
class LeadByPageChart extends ChartWidget
{
    protected static ?string $heading = 'Leads by landing page';

    protected static ?string $description = 'Last 90 days. Which page the form was submitted on.';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $rows = Lead::query()
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw("coalesce(nullif(source_page, ''), 'unknown') as bucket, count(*) as total")
            ->groupBy('bucket')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'bucket');

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => $rows->values()->all(),
                    'backgroundColor' => '#FF4D0A',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $rows->keys()->map(fn (string $page): string => '/'.ltrim($page, '/'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'maintainAspectRatio' => false,
        ];
    }

    public static function canView(): bool
    {
        return Lead::query()->exists();
    }
}
