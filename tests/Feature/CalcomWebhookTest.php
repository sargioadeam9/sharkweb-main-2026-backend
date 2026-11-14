<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Mail\BookingReminder;
use App\Models\Lead;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CalcomWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'calcom_test_secret_12345';

    protected function setUp(): void
    {
        parent::setUp();
        config(['sharkweb.calcom.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_unconfigured_secret_returns_503(): void
    {
        config(['sharkweb.calcom.webhook_secret' => null]);

        $response = $this->postJson('/api/v1/webhooks/calcom', ['triggerEvent' => 'BOOKING_CREATED']);

        $response->assertStatus(503)
            ->assertJsonPath('error', 'unconfigured');
    }

    public function test_invalid_signature_returns_401(): void
    {
        $response = $this->postJson(
            '/api/v1/webhooks/calcom',
            ['triggerEvent' => 'BOOKING_CREATED'],
            ['X-Cal-Signature-256' => 'invalid_signature_hash']
        );

        $response->assertStatus(401)
            ->assertJsonPath('error', 'invalid_signature');
    }

    public function test_valid_webhook_marks_lead_as_booked(): void
    {
        $lead = Lead::create([
            'name' => 'Sarah Connor',
            'email' => 'sarah@skynet-resistance.org',
            'status' => LeadStatus::Qualified,
        ]);

        $this->assertNull($lead->booked_at);

        $payload = [
            'triggerEvent' => 'BOOKING_CREATED',
            'payload' => [
                'attendees' => [
                    ['name' => 'Sarah Connor', 'email' => 'sarah@skynet-resistance.org'],
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, self::WEBHOOK_SECRET);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/calcom',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CAL_SIGNATURE_256' => $signature,
            ],
            $rawBody
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('matched', true)
            ->assertJsonPath('lead_id', $lead->id);

        $this->assertNotNull($lead->fresh()->booked_at);
    }

    public function test_booking_reminder_skips_lead_with_booked_at_set(): void
    {
        Mail::fake();

        $settings = app(GeneralSettings::class);
        $settings->resend_api_key = 're_test_key_not_real';
        $settings->save();

        // Create a lead invited 4 days ago but who booked 1 day ago
        $lead = Lead::create([
            'name' => 'Booked Client',
            'email' => 'booked@example.com',
            'status' => LeadStatus::Qualified,
        ]);
        $lead->qualified_invite_sent_at = Carbon::now()->subDays(4);
        $lead->booked_at = Carbon::now()->subDays(1);
        $lead->save();

        $this->artisan('leads:booking-reminder')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNull($lead->fresh()->booking_reminder_sent_at);
    }
}