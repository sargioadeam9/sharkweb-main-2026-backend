<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeadScore;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Services\MetaConversionsService;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Server-side conversion reporting.
 *
 * WHY THESE MATTER. This code talks to a paid platform with a credential that can write
 * into a pixel. Three things must hold: it stays silent unless the owner switched it on,
 * it never counts one lead twice, and it never costs a lead when Meta is unreachable.
 */
class MetaConversionsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $overrides = []): GeneralSettings
    {
        $settings = app(GeneralSettings::class);

        $settings->meta_pixel_id = $overrides['pixel'] ?? '1234567890123456';
        $settings->meta_capi_access_token = $overrides['token'] ?? 'test-token-value';
        $settings->meta_capi_enabled = $overrides['enabled'] ?? true;
        $settings->meta_capi_test_event_code = $overrides['test_code'] ?? null;
        $settings->save();

        return app(GeneralSettings::class);
    }

    private function lead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Zonaed Hossain',
            'email' => 'Zon@Example.com',
            'phone' => '+880 1712 345678',
            'goal' => 'NEW BUILD',
            'message' => 'A brief.',
            'ip_address' => '203.0.113.10',
            'source_page' => '/contact/',
            'utm_campaign' => 'meta_ads_october',
            'fbc' => 'fb.1.1700000000.IwAR123',
            'fbp' => 'fb.1.1700000000.987654321',
            'event_id' => 'e2f1c0de-0000-4000-8000-000000000001',
            'status' => LeadStatus::New,
            'lead_score' => LeadScore::Warm,
            'score_reasons' => [],
        ], $overrides));
    }

    /* ================================================================== *
     * Silence when it should be silent
     * ================================================================== */

    public function test_nothing_is_sent_when_the_toggle_is_off(): void
    {
        Http::fake();

        $service = new MetaConversionsService($this->settings(['enabled' => false]));
        $service->sendLead($this->lead());

        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_without_a_pixel_id(): void
    {
        Http::fake();

        $service = new MetaConversionsService($this->settings(['pixel' => '']));
        $service->sendLead($this->lead());

        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_without_a_token(): void
    {
        Http::fake();

        $service = new MetaConversionsService($this->settings(['token' => '']));
        $service->sendLead($this->lead());

        Http::assertNothingSent();
    }

    public function test_a_pixel_id_that_is_not_a_pixel_id_is_refused(): void
    {
        Http::fake();

        // The id is interpolated into the request URL, so anything but digits is refused
        // rather than sent.
        $service = new MetaConversionsService($this->settings(['pixel' => 'abc/../steal']));
        $service->sendLead($this->lead());

        Http::assertNothingSent();
    }

    /* ================================================================== *
     * The event itself
     * ================================================================== */

    public function test_it_sends_a_lead_event_with_the_stored_event_id(): void
    {
        Http::fake();

        $lead = $this->lead();
        (new MetaConversionsService($this->settings()))->sendLead($lead, 'Mozilla/5.0 Test');

        Http::assertSent(function ($request) use ($lead): bool {
            $event = $request['data'][0];

            return $request->url() === 'https://graph.facebook.com/v21.0/1234567890123456/events'
                && $event['event_name'] === 'Lead'
                && $event['action_source'] === 'website'
                // The deduplication key: the browser sends the same one from fbq().
                && $event['event_id'] === $lead->event_id
                && $event['event_source_url'] === rtrim((string) config('sharkweb.frontend_url'), '/').'/contact/'
                && $request['access_token'] === 'test-token-value';
        });
    }

    public function test_the_email_and_phone_are_hashed_not_sent_raw(): void
    {
        Http::fake();

        (new MetaConversionsService($this->settings()))->sendLead($this->lead());

        Http::assertSent(function ($request): bool {
            $user = $request['data'][0]['user_data'];

            // Lowercased and trimmed before hashing, which is Meta's requirement.
            return $user['em'][0] === hash('sha256', 'zon@example.com')
                && $user['ph'][0] === hash('sha256', '8801712345678')
                && ! str_contains($request->body(), 'Zon@Example.com')
                && ! str_contains($request->body(), '1712 345678');
        });
    }

    public function test_the_click_identifiers_and_client_details_are_forwarded(): void
    {
        Http::fake();

        (new MetaConversionsService($this->settings()))->sendLead($this->lead(), 'Mozilla/5.0 Test');

        Http::assertSent(function ($request): bool {
            $user = $request['data'][0]['user_data'];

            return $user['fbc'] === 'fb.1.1700000000.IwAR123'
                && $user['fbp'] === 'fb.1.1700000000.987654321'
                && $user['client_ip_address'] === '203.0.113.10'
                && $user['client_user_agent'] === 'Mozilla/5.0 Test';
        });
    }

    public function test_the_test_event_code_is_passed_when_set(): void
    {
        Http::fake();

        (new MetaConversionsService($this->settings(['test_code' => 'TEST12345'])))->sendLead($this->lead());

        Http::assertSent(fn ($request): bool => $request['test_event_code'] === 'TEST12345');
    }

    /* ================================================================== *
     * Failure must never cost a lead
     * ================================================================== */

    public function test_a_rejected_event_does_not_throw(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 400)]);

        (new MetaConversionsService($this->settings()))->sendLead($this->lead());

        $this->assertSame(1, Lead::query()->count());
    }

    public function test_a_network_failure_does_not_throw(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => throw new \RuntimeException('connection reset')]);

        (new MetaConversionsService($this->settings()))->sendLead($this->lead());

        $this->assertSame(1, Lead::query()->count());
    }

    /**
     * The end-to-end version of the same promise: submitting a brief succeeds even when Meta
     * is down, and the conversion is attempted exactly once.
     */
    public function test_a_brief_still_succeeds_when_meta_is_down(): void
    {
        config(['sharkweb.api_key' => 'test-api-key-'.str_repeat('x', 12)]);
        $this->settings();

        Http::fake(['graph.facebook.com/*' => Http::response('upstream error', 503)]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.config('sharkweb.api_key'),
            'Accept' => 'application/json',
        ])->postJson('/api/v1/leads', [
            'name' => 'Zonaed Hossain',
            'email' => 'zon@example.com',
            'goal' => 'NEW BUILD',
            'message' => 'A brief.',
            'ip_address' => '203.0.113.10',
            'fbc' => 'fb.1.1700000000.IwAR123',
        ])->assertCreated();

        $this->assertSame(1, Lead::query()->count());
        Http::assertSentCount(1);
    }

    public function test_the_click_identifiers_are_stored_on_the_lead(): void
    {
        config(['sharkweb.api_key' => 'test-api-key-'.str_repeat('x', 12)]);
        Http::fake();

        $this->withHeaders([
            'Authorization' => 'Bearer '.config('sharkweb.api_key'),
            'Accept' => 'application/json',
        ])->postJson('/api/v1/leads', [
            'name' => 'Zonaed Hossain',
            'email' => 'zon@example.com',
            'goal' => 'NEW BUILD',
            'message' => 'A brief.',
            'ip_address' => '203.0.113.10',
            'fbc' => 'fb.1.1700000000.IwAR123',
            'fbp' => 'fb.1.1700000000.987654321',
        ])->assertCreated();

        $this->assertDatabaseHas('leads', [
            'email' => 'zon@example.com',
            'fbc' => 'fb.1.1700000000.IwAR123',
            'fbp' => 'fb.1.1700000000.987654321',
        ]);
    }
}
