<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\LeadScore;
use App\Models\Lead;
use Filament\Widgets\ChartWidget;

/**
 * How good the enquiries are, not how many.
 *
 * WHY THIS DESERVES A WIDGET. Every other lead chart counts volume, and volume is the easy
 * thing to buy. Two hundred cheap clicks and twenty enquiries from people with a budget look
 * identical on a source chart; they are not the same business. `LeadScoringService` already
 * scores every lead on budget signals, urgency and how specific the brief is, so this is the
 * one view that says whether the traffic is worth having.
 *
 * 90 DAYS, MATCHING THE SOURCE CHART. Comparing a 30-day quality mix against a 90-day source
 * mix would invite exactly the wrong conclusion.
 */
class LeadQualityChart extends ChartWidget
{
    protected static ?string $heading = 'Lead quality';

    protected static ?string $description = 'Last 90 days, by score. Cold leads are usually budget or fit.';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $counts = Lead::query()
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('lead_score, count(*) as total')
            ->groupBy('lead_score')
            ->pluck('total', 'lead_score');

        // Hot first: the eye should land on what is working before what is not.
        $order = [LeadScore::Hot, LeadScore::Warm, LeadScore::Cold];

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => array_map(fn (LeadScore $s): int => (int) ($counts[$s->value] ?? 0), $order),
                    'backgroundColor' => ['#FF4D0A', '#D93600', '#A1A1A1'],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => array_map(fn (LeadScore $s): string => ucfirst($s->value), $order),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'cutout' => '60%',
            'maintainAspectRatio' => false,
        ];
    }

    public static function canView(): bool
    {
        return Lead::query()->exists();
    }
}
