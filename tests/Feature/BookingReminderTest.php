<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Mail\BookingReminder;
use App\Models\Lead;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The single follow-up for an invited lead who never booked.
 *
 * WHAT THESE GUARD. The command runs unattended every morning, so a mistake here is not one
 * wrong email — it is the same wrong email, to the same people, every day until someone
 * notices. Three things must hold: it waits three days, it never touches a lead the owner has
 * closed, and it never sends twice.
 */
class BookingReminderTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMailWithKey(): void
    {
        Mail::fake();

        $settings = app(GeneralSettings::class);
        $settings->resend_api_key = 're_test_key_not_real';
        $settings->save();
    }

    /** A lead the owner invited $daysAgo days ago. */
    private function invitedLead(int $daysAgo, array $overrides = []): Lead
    {
        $lead = Lead::create(array_merge([
            'name' => 'adeam sargio',
            'email' => 'adeam@example.com',
            'status' => LeadStatus::Qualified,
        ], $overrides));

        $lead->qualified_invite_sent_at = now()->subDays($daysAgo);
        $lead->save();

        return $lead;
    }

    public function test_it_reminds_a_lead_invited_three_days_ago_and_stamps_it(): void
    {
        $this->fakeMailWithKey();

        $lead = $this->invitedLead(4);

        $this->artisan('leads:booking-reminder')->assertExitCode(0);

        Mail::assertSent(BookingReminder::class, fn (BookingReminder $mail): bool => $mail->hasTo('adeam@example.com'));
        $this->assertNotNull($lead->fresh()->booking_reminder_sent_at);
    }

    public function test_it_leaves_a_lead_invited_yesterday_alone(): void
    {
        $this->fakeMailWithKey();

        $lead = $this->invitedLead(1);

        $this->artisan('leads:booking-reminder')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNull($lead->fresh()->booking_reminder_sent_at);
    }

    public function test_it_never_nudges_a_closed_or_spam_lead(): void
    {
        $this->fakeMailWithKey();

        $closed = $this->invitedLead(10, ['email' => 'closed@example.com', 'status' => LeadStatus::Closed]);
        $spam = $this->invitedLead(10, ['email' => 'spam@example.com', 'status' => LeadStatus::Spam]);

        $this->artisan('leads:booking-reminder')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNull($closed->fresh()->booking_reminder_sent_at);
        $this->assertNull($spam->fresh()->booking_reminder_sent_at);
    }

    public function test_it_does_not_remind_the_same_lead_twice(): void
    {
        $this->fakeMailWithKey();

        $lead = $this->invitedLead(5);

        $this->artisan('leads:booking-reminder')->assertExitCode(0);
        $this->artisan('leads:booking-reminder')->assertExitCode(0);

        Mail::assertSent(BookingReminder::class, 1);
        $this->assertNotNull($lead->fresh()->booking_reminder_sent_at);
    }

    public function test_dry_run_sends_nothing(): void
    {
        $this->fakeMailWithKey();

        $lead = $this->invitedLead(5);

        $this->artisan('leads:booking-reminder', ['--dry-run' => true])->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNull($lead->fresh()->booking_reminder_sent_at);
    }
}
