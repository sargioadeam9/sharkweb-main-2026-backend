<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\AlertOnBackupFailure;
use App\Models\Content;
use App\Observers\ContentObserver;
use App\Services\Maintenance\MySqlPureDumper;
use App\Services\Maintenance\SqlitePureDumper;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureEloquentStrictness();
        $this->configureBackupAlerts();
        $this->configureBackupDumpers();
        $this->configureActivityLog();
        $this->configureContentRevalidation();
        $this->configureResendCredential();
    }

    /**
     * Hand the panel's Resend key to Laravel's own mailer.
     *
     * `App\Services\ResendMailer` already does this per send, but that only covers the three
     * mails it sends. The moment `MAIL_MAILER` became `resend`, everything else — a Filament
     * password reset, a framework notification — went through the default mailer, which reads
     * `config('resend.api_key')`, which reads `RESEND_API_KEY` from `.env`, which is
     * deliberately empty. The result was `API key is invalid` on mail the owner never
     * triggered. Probed it: that is exactly what happened.
     *
     * FAILS SOFT ON PURPOSE. This runs on every request, including the first one after a
     * fresh clone where the settings table does not exist yet. A missing table must not turn
     * into a 500 on the whole site — the mail simply will not send, which is the state it was
     * already in.
     */
    private function configureResendCredential(): void
    {
        try {
            $key = (string) app(\App\Settings\GeneralSettings::class)->resend_api_key;
        } catch (\Throwable) {
            return;
        }

        if ($key !== '') {
            config(['resend.api_key' => $key]);
        }
    }

    /**
     * Purge the frontend's cache whenever content changes.
     *
     * ONE OBSERVER, SEVEN MODELS. `ContentObserver` maps a model to the cache tags and page
     * paths a change invalidates, so a save in Filament rebuilds exactly the affected pages
     * instead of waiting out the 300-second window. Registering it here rather than in each
     * model keeps the whole content module's invalidation policy in one readable place —
     * and means the importer, the bulk actions and drag-and-drop reordering all get it for
     * free, because they are all Eloquent writes.
     *
     * Registered unconditionally. The service itself checks `sharkweb.isr.enabled`, so a
     * local checkout without a reachable frontend logs a debug line and moves on rather
     * than needing the registration to be conditional too.
     */
    private function configureContentRevalidation(): void
    {
        /*
         * THE THREE HOMEPAGE BANDS WERE MISSING.
         *
         * `ContentObserver::targetsFor()` has had cases for `Stat`, `FeaturedCard` and
         * `Review` — `[['stats'], ['/']]` and friends — but this list never registered the
         * observer on those models, so those cases were unreachable. Editing Featured
         * projects, Homepage stats or Client reviews saved to the database and was served by
         * the API, and the site kept showing its cached copy until the 300-second window
         * lapsed. Probed it: renamed a featured card, the homepage still had the old name.
         */
        foreach ([
            Content\Service::class,
            Content\ServiceGroup::class,
            Content\Post::class,
            Content\CaseStudy::class,
            Content\OssProject::class,
            Content\Tier::class,
            Content\PageHeader::class,
            // The homepage's three bands.
            Content\Stat::class,
            Content\FeaturedCard::class,
            Content\Review::class,
        ] as $model) {
            $model::observe(ContentObserver::class);
        }
    }

    /**
     * Replace spatie's shell-based dumpers with pure-PHP ones.
     *
     * Both stock dumpers need binaries (`sqlite3`, `mysqldump`) that shared cPanel hosting
     * commonly blocks and that this machine partly lacks. Everything spatie does around the
     * dump — zip, encryption, `backup:clean` rotation, monitoring, notifications — is
     * untouched; only the statement-level dump is swapped. See App\Services\Maintenance.
     */
    private function configureBackupDumpers(): void
    {
        DbDumperFactory::extend('sqlite', fn () => new SqlitePureDumper);
        DbDumperFactory::extend('mysql', fn () => new MySqlPureDumper);
    }

    /**
     * Activity-log wiring that cannot live on a model trait (§Maintenance).
     *
     * Filament's login/logout happen outside any model save, so nothing would record
     * them otherwise — and "who was in the panel, when" is the first question any
     * incident review asks. Events are logged directly rather than through a dedicated
     * listener class because each entry is two lines and fails soft: the maintenance
     * module must never make login itself fail.
     *
     * `causedBy()`, NOT `causer()` — activitylog v4 has no `causer()` method, and because
     * these blocks fail soft the wrong name failed SILENTLY: no login, logout or settings
     * entry was ever written. `tests/Feature/ActivityTrailTest.php` now asserts each of the
     * three trails really lands, so a rename cannot quietly kill them again.
     */
    private function configureActivityLog(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            try {
                activity('filament')
                    ->event('login')
                    ->causedBy($event->user)
                    ->withProperties(['ip' => request()->ip()])
                    ->log('Signed in to the admin panel');
            } catch (\Throwable $e) {
                report($e);
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            try {
                activity('filament')
                    ->event('logout')
                    ->causedBy($event->user)
                    ->withProperties(['ip' => request()->ip()])
                    ->log('Signed out of the admin panel');
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Route backup failures to Telegram (§14.14).
     *
     * Registered as listeners rather than via spatie's mail-notification config because the
     * important failure mode — a nightly backup that silently stopped running — produces no
     * mail at all. Only `backup:monitor` catches it, so its result needs to reach a phone.
     * See App\Listeners\AlertOnBackupFailure.
     */
    private function configureBackupAlerts(): void
    {
        Event::listen([
            BackupHasFailed::class,
            UnhealthyBackupWasFound::class,
            CleanupHasFailed::class,
        ], AlertOnBackupFailure::class);
    }

    /**
     * Named rate limiters for the API (§4).
     *
     * Defined here rather than as a `throttle:5,10` string on the route so the numbers come
     * from config and can be tuned per environment without editing routes.
     *
     * KEYED ON THE VISITOR, NOT THE CALLER — see `visitorIp()`.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('leads', function (Request $request): Limit {
            return Limit::perMinutes(
                (int) config('sharkweb.leads.rate_limit.decay_minutes', 10),
                (int) config('sharkweb.leads.rate_limit.max_attempts', 5),
            )
                ->by($this->visitorIp($request))
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'error' => 'too_many_requests',
                        'message' => 'Too many submissions from this connection. Please try again shortly.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('newsletter', function (Request $request): Limit {
            return Limit::perMinutes(60, 3)
                ->by($this->visitorIp($request))
                ->response(fn (Request $request, array $headers) => response()->json([
                    'error' => 'too_many_requests',
                    'message' => 'Too many sign-up attempts. Please try again later.',
                ], 429, $headers));
        });

        // Cheap global ceiling on the API surface, so a misbehaving client cannot hammer
        // the origin. Generous — the real limits are per-endpoint. Attached to the
        // key-guarded route group (throttle:api in routes/api.php); the number lives in
        // config so the test suite can raise it and production can tune it.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(
            (int) config('sharkweb.api.rate_limit', 120),
        )->by($this->visitorIp($request)));

        // The tracking beacon, sent by the visitor's own browser on every page view. Keyed on
        // $request->ip() — this is the one route where the browser calls directly, so the peer
        // address is the real visitor (INVARIANTS §2: on the server-to-server routes it would
        // be the frontend's address). Generous enough that a person clicking through the site
        // never hits it; tight enough that a scripted spammer writes one row a second at most.
        RateLimiter::for('visitor', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) $request->ip()));

        // Order placement. Looser than the lead limiter because a genuine customer may
        // retry after a gateway decline, and locking them out would lose the sale.
        RateLimiter::for('orders', function (Request $request): Limit {
            return Limit::perMinutes(
                (int) config('sharkweb.shop.rate_limit.decay_minutes', 10),
                (int) config('sharkweb.shop.rate_limit.max_attempts', 10),
            )
                ->by($this->visitorIp($request))
                ->response(fn (Request $request, array $headers) => response()->json([
                    'error' => 'too_many_requests',
                    'message' => 'Too many order attempts from this connection. Please try again shortly.',
                ], 429, $headers));
        });
    }

    /**
     * The visitor's IP address, for rate-limit bucketing.
     *
     * WHY THIS IS NOT JUST `$request->ip()`.
     * The visitor's browser never calls this API. Every request arrives from the Next.js
     * server on Vercel, so `$request->ip()` is the *Vercel server's* address — identical for
     * every visitor on the planet. Keying a limiter on it gives the whole world one shared
     * bucket: ten orders in ten minutes from anywhere and every subsequent customer is
     * locked out with a 429. That is a site-wide denial of service caused by the rate
     * limiter itself, and it applied to leads and newsletter sign-ups too, not just orders.
     *
     * The visitor's real address arrives in one of two places, in order of preference:
     *
     *   1. `X-Forwarded-For`, set by our own Server Actions. The first entry is the client;
     *      the rest are proxies.
     *   2. The validated `ip_address` body field, which `StoreLeadRequest` and
     *      `StoreOrderRequest` accept for exactly this purpose.
     *
     * Both are caller-supplied, so both are only as trustworthy as the API key — which is
     * the point: only our own frontend can reach these routes at all, so a value it sets is
     * trusted to the same degree the key is. A malformed value falls through to the peer
     * address rather than creating a bucket per request. If this API ever becomes
     * multi-tenant or publicly reachable, this needs revisiting.
     */
    private function visitorIp(Request $request): string
    {
        // HOSTING ASSUMPTION (deliberate): the FIRST X-Forwarded-For entry is the
        // visitor. This is correct while the frontend runs on Vercel, whose edge
        // controls the header and does not let a client prepend a spoofable value.
        // If the frontend ever moves behind a proxy that APPENDS to the header
        // (typical nginx config) instead of overwriting it, the first entry becomes
        // client-controlled and a spammer could rotate it to defeat the per-visitor
        // buckets — switch to the LAST entry (added by the nearest trusted proxy) at
        // that point, and set TRUSTED_PROXIES so $request->ip() is honest too.
        $forwarded = $request->header('X-Forwarded-For');

        if (is_string($forwarded) && $forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);

            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        $body = $request->input('ip_address');

        if (is_string($body) && filter_var($body, FILTER_VALIDATE_IP) !== false) {
            return $body;
        }

        return (string) $request->ip();
    }

    /**
     * Fail loudly on N+1 queries and silently-discarded attributes outside production.
     *
     * This is what catches a missing `with()` in an API controller during development
     * rather than in production under load. Scoped to local so production is never taken
     * down by an over-eager guard.
     */
    private function configureEloquentStrictness(): void
    {
        Model::preventLazyLoading($this->app->isLocal());
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());
    }
}
