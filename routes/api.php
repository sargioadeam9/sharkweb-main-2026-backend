<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CalcomWebhookController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Headless REST API v1
|--------------------------------------------------------------------------
|
| The frontend's whole surface. `GET /settings` for the shell (contact,
| footer, SEO, social), `POST /leads` for the brief form, `POST /newsletter`
| for the sign-up form, and `GET /content/*` for the six pages the owner
| edits in the panel.
|
| THE CONTENT READS CAME BACK, 2026-10-24. They were trimmed on 2026-10-23
| along with the shop, the visitor beacon and the SEO/redirect endpoints,
| because at that point the page copy was compiled into `lib/*.ts` and the
| endpoints served data nothing rendered. That is no longer true: services,
| newsletter issues, case studies, open-source tiles, commitment tiers and
| the four page headers now live in `content_*` tables and the panel is the
| source of truth. The TS modules are the fallback for a backend outage.
|
| Everything here is server-to-server and sits behind `api.key`. An
| unauthenticated read of the lead database would be as bad as an
| unauthenticated write.
|
| `/health` is deliberately outside the auth group so an uptime monitor can
| reach it — no auth, no data, no cost.
|
*/

Route::prefix('v1')->group(function (): void {

    // Uptime + deployment verification. No auth, no data, no cost.
    Route::get('/health', HealthController::class)->name('api.health');

    // Inbound Cal.com booking webhook (verified by shared secret, not api.key).
    Route::post('/webhooks/calcom', CalcomWebhookController::class)
        ->middleware('throttle:api')
        ->name('api.webhooks.calcom');

    Route::middleware(['api.key', 'throttle:api'])->group(function (): void {

        // Settings drive the whole frontend shell: contact, footer, SEO, social.
        Route::get('/settings', SettingController::class)->name('api.settings');

        // 5 submissions per 10 minutes per IP (§4). Named limiter defined in
        // AppServiceProvider so the numbers live in config, not in a route string.
        Route::post('/leads', [LeadController::class, 'store'])
            ->middleware('throttle:leads')
            ->name('api.leads.store');

        Route::post('/newsletter', [NewsletterController::class, 'store'])
            ->middleware('throttle:newsletter')
            ->name('api.newsletter.store');

        /* -------------------------------------------------------------- *
         | Content reads
         |
         | The six pages the owner edits in the panel. Read-only, one
         | endpoint per page, cached on the frontend under the tags the
         | ContentObserver purges on save.
         |
         | These were removed on 2026-10-23 when the copy was still
         | compiled into `lib/*.ts`. They are back because the content now
         | lives in the database and the panel is the source of truth —
         | the TS modules are the fallback, not the original.
         * -------------------------------------------------------------- */
        Route::prefix('content')->name('api.content.')->group(function (): void {
            Route::get('/services', [ContentController::class, 'services'])->name('services');
            Route::get('/services/{slug}', [ContentController::class, 'service'])->name('service');

            Route::get('/newsletter', [ContentController::class, 'newsletter'])->name('newsletter');
            Route::get('/newsletter/{slug}', [ContentController::class, 'newsletterPost'])->name('newsletter.post');

            Route::get('/work', [ContentController::class, 'work'])->name('work');
            Route::get('/work/{slug}', [ContentController::class, 'workCase'])->name('work.case');

            Route::get('/open-source', [ContentController::class, 'openSource'])->name('open-source');

            Route::get('/tiers', [ContentController::class, 'tiers'])->name('tiers');

            Route::get('/stats', [ContentController::class, 'stats'])->name('stats');
            Route::get('/featured', [ContentController::class, 'featured'])->name('featured');
            Route::get('/reviews', [ContentController::class, 'reviews'])->name('reviews');

            Route::get('/page-headers', [ContentController::class, 'pageHeaders'])->name('page-headers');
        });
    });
});
