<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Mail\QualifiedInvite;
use App\Models\Lead;
use App\Services\LeadService;
use App\Services\ResendMailer;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * The approve-and-invite path.
 *
 * WHAT THESE GUARD. The button sends an email to a real person, so the two ways it can go
 * wrong are both silent:
 *
 *   1. The stamp is written even though the send failed — the lead then looks invited for
 *      ever, the button says "Resend invite" and nobody notices the person was never told.
 *   2. The email is sent but the lead is left in New — the pipeline disagrees with reality.
 *
 * The second test is the important one.
 */
class QualifiedInviteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A key has to exist before `Mail::fake()` can help.
     *
     * `ResendMailer` refuses to build a client without one, and that refusal happens in our
     * code, before the mailer is ever touched — so a faked mailer alone still throws. The
     * dummy key is never sent anywhere: the fake records the message and stops.
     */
    private function fakeMailWithKey(): void
    {
        Mail::fake();

        $settings = app(GeneralSettings::class);
        $settings->resend_api_key = 're_test_key_not_real';
        $settings->save();
    }

    private function lead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'adeam sargio',
            'email' => 'adeam@example.com',
            'goal' => 'CONSULT',
            'message' => "Needs help with paid media.\n\nTimeline: 1-3 MONTHS",
            'status' => LeadStatus::New,
        ], $overrides));
    }

    public function test_it_emails_the_lead_and_stamps_the_invite(): void
    {
        $this->fakeMailWithKey();

        $lead = $this->lead();

        app(LeadService::class)->sendQualifiedInvite($lead);

        Mail::assertSent(QualifiedInvite::class, fn (QualifiedInvite $mail): bool => $mail->hasTo('adeam@example.com'));

        $fresh = $lead->fresh();

        $this->assertNotNull($fresh->qualified_invite_sent_at);
        $this->assertSame(LeadStatus::Qualified, $fresh->status);
    }

    public function test_a_failed_send_stamps_nothing_and_leaves_the_status_alone(): void
    {
        Mail::fake();

        $this->mock(ResendMailer::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('Resend refused the message.'));
        });

        $lead = $this->lead();

        try {
            app(LeadService::class)->sendQualifiedInvite($lead);
            $this->fail('A refused send must not be swallowed here.');
        } catch (RuntimeException) {
            // Expected: the panel action turns this into a danger notification.
        }

        $fresh = $lead->fresh();

        $this->assertNull($fresh->qualified_invite_sent_at, 'A lead must never look invited after a failed send.');
        $this->assertSame(LeadStatus::New, $fresh->status);
    }

    public function test_a_closed_lead_is_refused_even_if_something_calls_the_service_directly(): void
    {
        $this->fakeMailWithKey();

        $lead = $this->lead(['status' => LeadStatus::Closed]);

        try {
            app(LeadService::class)->sendQualifiedInvite($lead);
            $this->fail('A closed lead must not be invited.');
        } catch (RuntimeException) {
            // Expected: the panel hides the button, but the service is the real guard.
        }

        Mail::assertNothingSent();
        $this->assertSame(LeadStatus::Closed, $lead->fresh()->status);
        $this->assertNull($lead->fresh()->qualified_invite_sent_at);
    }

    public function test_the_booking_link_carries_the_lead_name_and_email(): void
    {
        $this->fakeMailWithKey();

        $lead = $this->lead();

        app(LeadService::class)->sendQualifiedInvite($lead);

        Mail::assertSent(QualifiedInvite::class, function (QualifiedInvite $mail) use ($lead): bool {
            $html = $mail->render();

            return str_contains($html, 'cal.com/thesharkweb/30min')
                && str_contains($html, rawurlencode($lead->email))
                && str_contains($html, 'name=');
        });
    }
}
