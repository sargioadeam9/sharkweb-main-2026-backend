<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Mail\DailyReport as DailyReportMail;
use App\Models\Lead;
use App\Models\NewsletterSubscriber;
use App\Models\WebhookLog;
use App\Services\ResendMailer;
use App\Settings\GeneralSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The 23:30 KPI digest (§Daily report).
 *
 * WHY THIS EXISTS AT ALL. The settings, the config block and the comment describing the
 * screen were all in place; the command was not. For a week the panel's switch said "on"
 * and nothing was ever sent.
 *
 * THE DAY IS A DHAKA DAY. `created_at` is stored in UTC, so the window is built in the
 * configured timezone and converted by Carbon on the way out. A UTC window would cut the
 * day at 06:00 local and push the evening's leads into "tomorrow" — the exact thing the
 * owner would be reading this mail to check.
 *
 * RESEND IS EXPLICIT. `ResendMailer` rather than whatever `MAIL_MAILER` happens to be: the
 * default is `log` on this machine, and a report that "sent successfully" into a log file is
 * worse than no report. The same service now carries the lead alerts, so all three messages
 * share one credential — the one the owner rotates in the panel.
 */
class SendDailyReport extends Command
{
    protected $signature = 'report:daily
                            {--force : Send even when the nightly switch is off}
                            {--to= : Send to this address instead of the saved one (the panel test button)}';

    protected $description = 'Email the day\'s leads, subscribers and failures';

    public function handle(GeneralSettings $settings, ResendMailer $resend): int
    {
        $to = trim((string) $this->option('to')) ?: $settings->daily_report_recipient;

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("Not a valid email address: '{$to}'");

            return self::FAILURE;
        }

        if (! $settings->daily_report_enabled && ! $this->option('force')) {
            $this->info('The nightly report is switched off in Settings → Daily report.');

            return self::SUCCESS;
        }

        /*
         * THE KEY COMES FROM THE PANEL FIRST, `.env` SECOND — resolved in `ResendMailer`,
         * which every outbound mail now shares. The owner rotates it from Settings → Daily
         * report, so a revoked key is a two-minute fix rather than an SSH session.
         */
        if (! $resend->configured()) {
            $this->error('No Resend API key. Add one in the panel under Settings → Daily report, or set RESEND_API_KEY in .env.');

            return self::FAILURE;
        }

        try {
            $resend->send($to, new DailyReportMail($this->buildReport()));
        } catch (\Throwable $e) {
            // Surfaced rather than swallowed: this is what the panel's test button shows,
            // and an unverified Resend domain fails here with a 403.
            report($e);
            $this->error('Send failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Daily report sent to {$to}.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(): array
    {
        $timezone = (string) config('sharkweb.daily_report.timezone', 'Asia/Dhaka');

        $now = Carbon::now($timezone);
        $startOfToday = $now->copy()->startOfDay();
        $startOfYesterday = $startOfToday->copy()->subDay();

        $leadsToday = Lead::query()->where('created_at', '>=', $startOfToday)->count();
        $leadsYesterday = Lead::query()
            ->whereBetween('created_at', [$startOfYesterday, $startOfToday])
            ->count();

        $hotToday = Lead::query()->where('created_at', '>=', $startOfToday)->hot()->count();

        $byStatus = Lead::query()
            ->where('created_at', '>=', $startOfToday)
            ->get()
            ->countBy(fn (Lead $lead): string => $lead->status?->getLabel() ?? 'New')
            ->all();

        $subscribersToday = NewsletterSubscriber::query()
            ->where('created_at', '>=', $startOfToday)
            ->count();

        $failedWebhooks = WebhookLog::query()
            ->where('created_at', '>=', $now->copy()->subDay())
            ->where(function ($query): void {
                $query->whereNull('response_status')->orWhere('response_status', '>=', 400);
            })
            ->count();

        return [
            'date' => $now->format('D, j M Y'),
            'leads_today' => $leadsToday,
            'leads_yesterday' => $leadsYesterday,
            // The direction of the day, which is the only comparison worth printing.
            'lead_delta' => $leadsToday - $leadsYesterday,
            'hot_today' => $hotToday,
            'by_status' => $byStatus,
            'subscribers_today' => $subscribersToday,
            'subscribers_total' => NewsletterSubscriber::query()->count(),
            'open_leads' => Lead::query()->open()->count(),
            'failed_webhooks' => $failedWebhooks,
            'capi_enabled' => app(GeneralSettings::class)->meta_capi_enabled,
            'panel_url' => rtrim((string) config('app.url'), '/').'/theshark-hq',
        ];
    }
}
