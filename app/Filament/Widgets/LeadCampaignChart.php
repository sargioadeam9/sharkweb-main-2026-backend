<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

/**
 * Which campaign produced the leads, not just which network.
 *
 * WHY THIS SITS BESIDE THE SOURCE CHART RATHER THAN REPLACING IT. "facebook" is a channel;
 * "meta_ads_october" is the thing that can be switched off. The channel chart answers where
 * the budget is going, this one answers which spend inside it is working, and the second
 * question is the one you can act on tomorrow.
 *
 * ONLY MEANINGFUL NOW. Until campaign capture was added to the form, every `utm_campaign`
 * was null and this chart would have been a single grey ring. It is worth having only
 * because the landing-page parameters are now carried through to the submission.
 *
 * BLANKS ARE THEIR OWN BUCKET, the same way the source chart treats them. A campaign chart
 * that silently drops the untagged leads overstates every campaign that is tagged.
 */
class LeadCampaignChart extends ChartWidget
{
    protected static ?string $heading = 'Leads by campaign';

    protected static ?string $description = 'Last 90 days, grouped by UTM campaign.';

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 1;

    /** Beyond this the ring becomes a colour wheel nobody can read. */
    private const MAX_BUCKETS = 7;

    protected function getData(): array
    {
        $rows = Lead::query()
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw("coalesce(nullif(utm_campaign, ''), 'untagged') as bucket, count(*) as total")
            ->groupBy('bucket')
            ->orderByDesc('total')
            ->pluck('total', 'bucket');

        // Fold the long tail into one bucket rather than assigning seven shades of orange.
        if ($rows->count() > self::MAX_BUCKETS) {
            $head = $rows->take(self::MAX_BUCKETS - 1);
            $tail = $rows->slice(self::MAX_BUCKETS - 1);

            $rows = $head->put('other ('.($rows->count() - self::MAX_BUCKETS + 1).')', $tail->sum());
        }

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
            'plugins' => ['legend' => ['position' => 'bottom']],
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
        $colours = ['#FF4D0A', '#FF5A0A', '#D93600', '#F5F5F5', '#A1A1A1', '#737373', '#1C1D20'];

        $out = [];

        for ($i = 0; $i < max(1, $count); $i++) {
            $out[] = $colours[$i % count($colours)];
        }

        return $out;
    }

    public static function canView(): bool
    {
        return Lead::query()->exists();
    }
}
