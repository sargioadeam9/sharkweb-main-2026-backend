<?php

declare(strict_types=1);

use App\Models\WebhookLog;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Shared cPanel hosting has no queue worker, so every recurring job is a
| scheduled command driven by a single cron entry:
|
|   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
|
| See docs/BACKEND_MVP_PLAN.md A 8.4.
|
*/

// Retry newsletter sign-ups that Brevo rejected or could not be reached for.
Schedule::command('sharkweb:sync-newsletter')
    ->hourly()
    ->withoutOverlapping();

// Prune the append-only webhook log so it cannot grow without bound on a small
// shared-hosting disk quota. Failed dispatches are kept longer than successes.
Schedule::command('model:prune', ['--model' => [WebhookLog::class]])
    ->daily()
    ->at('03:15');

// Prune enquiries older than 24 months (730 days) to keep the privacy policy retention promise.
// Initial rollout: runs in --dry-run mode for safety observation before live deletion is enabled.
Schedule::command('leads:prune', ['--dry-run' => true])
    ->daily()
    ->at('03:20');

// The nightly KPI digest.
//
// The switch and the recipient are settings, not config — changing where a report goes
// must not need a deploy. Only the time shape is code, because the scheduler reads it when
// it builds its list. `timezone()` is explicit: the server runs UTC, and a 23:30 UTC send
// would land at 05:30 in Dhaka and report the wrong day.
Schedule::command('report:daily')
    ->dailyAt((string) config('sharkweb.daily_report.send_at', '23:30'))
    ->timezone((string) config('sharkweb.daily_report.timezone', 'Asia/Dhaka'))
    ->withoutOverlapping();

// The one follow-up for an invited lead who never booked.
//
// Mid-morning Dhaka time, on purpose: a reminder at 03:00 is a reminder nobody reads, and
// this one is competing with the rest of their inbox rather than with their sleep. The
// three-day delay and the "only once" stamp live in the command.
Schedule::command('leads:booking-reminder')
    ->dailyAt('10:00')
    ->timezone((string) config('sharkweb.daily_report.timezone', 'Asia/Dhaka'))
    ->withoutOverlapping();