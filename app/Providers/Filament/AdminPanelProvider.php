<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The Shark Web admin panel.
 *
 * Branding follows docs/DESIGNINSTRUCTION.md: a dark editorial surface built on the
 * Shark Web accent orange (#FF4D0A), with Inter as the UI typeface.
 *
 * Dark mode is FORCED (`darkMode(true, isForced: true)`) rather than offered as a toggle.
 * The design system has no light variant — every surface token in the constitution is a
 * near-black — so a light toggle would render the panel in a theme that does not exist.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            // Aliased away from /admin to cut down automated scanning (§7.4).
            ->path((string) config('sharkweb.filament.path', 'theshark-hq'))
            ->login()
            ->brandName((string) config('sharkweb.filament.brand_name', 'The Shark Web HQ'))

            // Primary brand accent. Status colours (danger/warning/success/info) are left
            // at Filament's defaults on purpose — they carry semantic meaning for the lead
            // score and status badges, so they must stay conventionally readable.
            ->colors([
                'primary' => Color::hex('#FF4D0A'),
                'gray' => Color::hex('#737373'),
            ])

            ->font('Inter')
            ->darkMode(true, isForced: true)

            /*
             * The sidebar order is an argument about what the panel is for.
             *
             * It used to fall back to Filament's own ordering, which put Maintenance —
             * the rarest and most destructive section — directly under the dashboard, and
             * buried Leads, the thing that gets opened every morning, below Settings.
             *
             * Now it runs from daily to never: the pipeline you answer, the content you
             * publish, the homepage bands, the service catalogue, then configuration, and
             * finally the pages that can overwrite state. Anything that is not listed here
             * still appears, after these, in discovery order.
             */
            ->navigationGroups([
                'Pipeline',
                'Content',
                'Homepage',
                'Services',
                'Settings',
                'Maintenance',
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            /*
             * NO `->widgets([...])` CALL, DELIBERATELY.
             *
             * This used to register Filament's `AccountWidget` — the "Welcome / Sign out" card.
             * It earned its place on a fresh panel and stopped earning it here: it said nothing
             * an admin does not already know, it sat above every real number on the dashboard,
             * and Sign out is in the user menu at the top right. `discoverWidgets()` still
             * registers everything in `app/Filament/Widgets`, so the dashboard is unaffected
             * apart from the card being gone.
             */
            ->renderHook(
                // Sits immediately before the global search, which is where a greeting belongs
                // on a panel topbar: visible on every screen, in the space that is otherwise
                // empty on a wide monitor.
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): View => view('filament.topbar-greeting'),
            )
            ->renderHook(
                /*
                 * A warning strip on every screen while ISR is switched off.
                 *
                 * Without it, a disabled webhook is invisible: the save succeeds, nothing
                 * logs, and the public page keeps its cached copy for five minutes — which
                 * reads as "my edit did not save". The view renders nothing when the flag is
                 * on, so this costs one config read per page and no markup in the normal case.
                 */
                PanelsRenderHook::PAGE_START,
                fn (): View => view('filament.isr-disabled'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
