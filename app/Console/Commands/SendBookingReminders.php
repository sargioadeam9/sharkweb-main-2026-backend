<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Mail\BookingReminder;
use App\Models\Lead;
use App\Services\ResendMailer;
use Illuminate\Console\Command;
use Throwable;

/**
 * The single follow-up for a lead who was invited and never booked.
 *
 * ONE REMINDER, EVER. `booking_reminder_sent_at` is the guard, and it is only written after a
 * successful send — so a Resend outage means the person is tried again tomorrow, not that
 * they are silently skipped.
 *
 * IT CANNOT KNOW WHO BOOKED. Cal.com is not wired back into this app, so the window is time
 * based: invited at least `--days` ago, never reminded. Someone who booked and ignored the
 * confirmation will get this too, which is why the copy tells them to ignore it.
 *
 * NOTHING CLOSED OR SPAM IS TOUCHED. Those are the owner's own decisions about the lead, and
 * a nightly command has no business overruling them.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'leads:booking-reminder
                            {--days=3 : How long after the invitation to nudge}
                            {--limit=50 : Most reminders in one run}
                            {--dry-run : List who would be emailed, send nothing}';

    protected $description = 'Nudge invited leads who have not booked';

    public function handle(ResendMailer $resend): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $cutoff = now()->subDays($days);

        $leads = Lead::query()
            ->whereNotNull('qualified_invite_sent_at')
            ->whereNull('booking_reminder_sent_at')
            ->whereNull('booked_at')
            ->where('qualified_invite_sent_at', '<=', $cutoff)
            ->whereNotIn('status', [LeadStatus::Closed, LeadStatus::Spam])
            ->whereNotNull('email')
            ->orderBy('qualified_invite_sent_at')
            ->limit($limit)
            ->get();

        if ($leads->isEmpty()) {
            $this->info('No leads are waiting for a reminder.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($leads as $lead) {
                $this->line("would remind: {$lead->email} (invited {$lead->qualified_invite_sent_at?->diffForHumans()})");
            }

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($leads as $lead) {
            try {
                $resend->send((string) $lead->email, new BookingReminder($lead));

                // Stamped only now: a failed send must leave the lead in the queue.
                $lead->booking_reminder_sent_at = now();
                $lead->save();

                $sent++;
            } catch (Throwable $e) {
                report($e);
                $this->warn("failed: {$lead->email} — {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Reminders sent: {$sent}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
