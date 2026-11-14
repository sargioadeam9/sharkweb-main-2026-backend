<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

/**
 * Which channel is producing leads (§11 — Lead Source Chart, §14.1).
 *
 * A doughnut rather than a bar chart because the useful question is proportional: "what
 * share of our pipeline is paid social versus direct?" A bar chart of the same numbers
 * makes that comparison harder to read at a glance.
 *
 * Direct/none is kept as an explicit bucket. Hiding it would make the paid channels look
 * like a larger share of the total than they are.
 */
class LeadSourceChart extends ChartWidget
{
    protected static ?string $heading = 'Leads by channel';

    protected static ?string $description = 'Last 90 days, grouped by UTM source.';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $rows = Lead::query()
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw("coalesce(nullif(utm_source, ''), 'direct / none') as bucket, count(*) as total")
            ->groupBy('bucket')
            ->orderByDesc('total')
            ->pluck('total', 'bucket');

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => $rows->values()->all(),
                    'backgroundColor' => $this->palette($rows->count()),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $rows->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
            // A doughnut with a thick ring is easier to read than a pie at this size.
            'cutout' => '60%',
            'maintainAspectRatio' => false,
        ];
    }

    /**
     * Brand-led palette, cycling when there are more buckets than colours.
     *
     * @return list<string>
     */
    private function palette(int $count): array
    {
        $colours = [
            '#FF4D0A', // Shark Web accent
            '#FF5A0A',
            '#D93600',
            '#F5F5F5',
            '#A1A1A1',
            '#737373',
            '#1C1D20',
        ];

        $out = [];

        for ($i = 0; $i < max(1, $count); $i++) {
            $out[] = $colours[$i % count($colours)];
        }

        return $out;
    }

    /** Pointless to render an empty ring on a fresh install. */
    public static function canView(): bool
    {
        return Lead::query()->exists();
    }
}
