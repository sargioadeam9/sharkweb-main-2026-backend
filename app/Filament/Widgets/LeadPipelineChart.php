<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Widgets\ChartWidget;

/**
 * Where leads are piling up: new, contacted, qualified, closed.
 *
 * WHY A BAR AND NOT A FUNNEL DIAGRAM. A real funnel drawing implies that every lead flows
 * from one stage to the next in order, which is not how this pipeline works — a lead can go
 * straight from new to closed, and spam never enters the pipeline at all. A bar chart of the
 * current counts tells the truth without inventing a flow.
 *
 * THE ORDER IS THE PIPELINE'S, NOT THE SORT ORDER. Alphabetical would put "closed" before
 * "new" and make the chart unreadable at a glance.
 *
 * SPAM IS EXCLUDED FROM THE BARS AND REPORTED IN THE DESCRIPTION. Drawing it beside the real
 * stages makes the pipeline look bigger than it is; hiding it entirely would make the numbers
 * not add up to the leads list.
 */
class LeadPipelineChart extends ChartWidget
{
    protected static ?string $heading = 'Pipeline';

    protected static ?int $sort = 5;

    // Full width: at half width this chart is followed by a full-width table and leaves a
    // dead half-row beside it. Four bars also read better across the page.
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $counts = Lead::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stages = [
            LeadStatus::New,
            LeadStatus::Contacted,
            LeadStatus::Qualified,
            LeadStatus::Closed,
        ];

        $spam = (int) ($counts[LeadStatus::Spam->value] ?? 0);

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => array_map(fn (LeadStatus $s): int => (int) ($counts[$s->value] ?? 0), $stages),
                    'backgroundColor' => ['#FF4D0A', '#D93600', '#A1A1A1', '#1C1D20'],
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_map(fn (LeadStatus $s): string => ucfirst($s->value), $stages),
        ];
    }

    /**
     * Spam is reported here rather than drawn, so the bars stay a picture of the pipeline.
     *
     * Overridden rather than assigned to the static property inside `getData()`, because
     * `getData()` is called from the view and the description may already have been read by
     * then — a race that would show the wrong text about one time in five.
     */
    public function getDescription(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $spam = Lead::query()->where('status', LeadStatus::Spam)->count();

        return $spam > 0
            ? "Current status of every lead. {$spam} marked spam, excluded."
            : 'Current status of every lead.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'maintainAspectRatio' => false,
        ];
    }

    public static function canView(): bool
    {
        return Lead::query()->exists();
    }
}
