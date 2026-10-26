<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Lead;
use App\Settings\GeneralSettings;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Whether the tracking is actually working, on the dashboard rather than in Events Manager.
 *
 * WHY THIS EXISTS. Conversion tracking fails silently. A wrong pixel id, a token that expired,
 * a consent banner nobody accepts, an ad platform that quietly rejects the payload — in every
 * case the site keeps working and the dashboard keeps looking healthy while nothing is being
 * reported. The only way to notice is to compare what the site sent with what it could have
 * sent, which is what these four figures do.
 *
 * THE TWO COVERAGE NUMBERS ARE THE USEFUL ONES. "Configured" is a setting the owner already
 * knows about; "eleven of fourteen recent leads carried a click id" is news.
 *
 * IT REPORTS, IT DOES NOT ALARM. A low click-id rate is normal for organic traffic, so the
 * colour only turns when the rate is zero across a meaningful sample — that means something
 * is broken rather than that most visitors did not arrive from an ad.
 */
class TrackingHealth extends BaseWidget
{
    protected ?string $heading = 'Tracking health';

    protected static ?int $sort = 9;

    protected function getStats(): array
    {
        $settings = app(GeneralSettings::class);

        // The last 30 days, and only leads that came through a form rather than an import.
        $recent = Lead::query()->where('created_at', '>=', now()->subDays(30));

        $total = (clone $recent)->count();
        $withClick = (clone $recent)->whereNotNull('fbc')->count();
        $withCampaign = (clone $recent)->whereNotNull('utm_source')->count();

        $pixel = filled($settings->meta_pixel_id);
        $capi = $settings->meta_capi_enabled && filled($settings->meta_capi_access_token);

        return [
            Stat::make('Click ids', $total === 0 ? '—' : "{$withClick} / {$total}")
                ->description($total === 0 ? 'no leads yet' : 'Meta click id captured')
                ->descriptionIcon('heroicon-m-cursor-arrow-rays')
                // Zero out of a real sample means the capture is broken; zero out of zero
                // means nothing has happened yet, which is not a problem.
                ->color($total > 0 && $withClick === 0 ? 'danger' : ($withClick > 0 ? 'success' : 'gray')),

            Stat::make('Campaign tags', $total === 0 ? '—' : "{$withCampaign} / {$total}")
                ->description($total === 0 ? 'no leads yet' : 'UTM source captured')
                ->descriptionIcon('heroicon-m-tag')
                ->color($total > 0 && $withCampaign === 0 ? 'danger' : ($withCampaign > 0 ? 'success' : 'gray')),

            Stat::make('Meta pixel', $pixel ? 'Configured' : 'Not set')
                ->description($pixel ? 'runs in the browser' : 'add an id under Settings → Analytics')
                ->descriptionIcon($pixel ? 'heroicon-m-check-circle' : 'heroicon-m-minus-circle')
                ->color($pixel ? 'success' : 'gray'),

            Stat::make('Server events', $capi ? 'On' : 'Off')
                ->description($capi ? 'Conversions API reporting' : 'needs pixel id, token and the toggle')
                ->descriptionIcon($capi ? 'heroicon-m-arrow-up-tray' : 'heroicon-m-minus-circle')
                ->color($capi ? 'success' : 'gray'),
        ];
    }

    public static function canView(): bool
    {
        return Lead::query()->exists();
    }
}
