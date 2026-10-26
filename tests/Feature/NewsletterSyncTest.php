<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Services\BrevoNewsletterService;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Newsletter sync with Brevo.
 *
 * WHY THESE MATTER. Every failure mode here is silent: the subscriber is stored locally first,
 * so the visitor is always told "you are on the list" whether or not Brevo ever heard of them.
 * The only way to know the sync works is to assert on it.
 *
 * The credential now lives in the panel, so these also pin the precedence rule — the panel
 * wins over `.env` — and the "not configured" path, which used to report itself as a Brevo
 * rejection.
 */
class NewsletterSyncTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://api.brevo.com/v3/contacts';

    private function configure(string $key = 'xkeysib-test', string $listId = '7'): void
    {
        $settings = app(GeneralSettings::class);
        $settings->brevo_api_key = $key;
        $settings->brevo_list_id = $listId;
        $settings->save();
    }

    private function subscriber(string $email = 'reader@example.com'): NewsletterSubscriber
    {
        return NewsletterSubscriber::create([
            'email' => $email,
            'source_page' => '/newsletter/',
            'subscribed_at' => now(),
        ]);
    }

    public function test_it_is_not_configured_out_of_the_box(): void
    {
        $this->assertFalse(app(BrevoNewsletterService::class)->configured());
    }

    public function test_a_panel_key_makes_it_configured(): void
    {
        $this->configure();

        $this->assertTrue(app(BrevoNewsletterService::class)->configured());
        $this->assertSame('xkeysib-test', app(BrevoNewsletterService::class)->key());
        $this->assertSame(7, app(BrevoNewsletterService::class)->listId());
    }

    public function test_an_unconfigured_sync_touches_no_network(): void
    {
        Http::fake();

        $subscriber = $this->subscriber();

        $this->assertFalse(app(BrevoNewsletterService::class)->sync($subscriber));
        $this->assertFalse($subscriber->fresh()->brevo_synced);

        Http::assertNothingSent();
    }

    public function test_a_configured_sync_pushes_the_contact_and_marks_it_synced(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['id' => 42], 201)]);
        $this->configure();

        $subscriber = $this->subscriber();

        $this->assertTrue(app(BrevoNewsletterService::class)->sync($subscriber));

        Http::assertSent(fn ($request): bool => $request->url() === self::ENDPOINT
            && $request->hasHeader('api-key', 'xkeysib-test')
            && $request['email'] === 'reader@example.com'
            && $request['listIds'] === [7]);

        $fresh = $subscriber->fresh();
        $this->assertTrue($fresh->brevo_synced);
        $this->assertTrue($fresh->is_verified);
    }

    public function test_a_rejection_leaves_the_subscriber_pending(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['message' => 'nope'], 400)]);
        $this->configure();

        $subscriber = $this->subscriber();

        $this->assertFalse(app(BrevoNewsletterService::class)->sync($subscriber));
        $this->assertFalse($subscriber->fresh()->brevo_synced);
    }

    public function test_the_hourly_command_stays_quiet_and_succeeds_when_unconfigured(): void
    {
        Http::fake();

        $subscriber = $this->subscriber();

        // Exit 0 on purpose: a missing key is a setting, not a broken job. It used to report
        // "failed" every hour, which is a cron mail an hour about nothing.
        $this->artisan('sharkweb:sync-newsletter')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertFalse($subscriber->fresh()->brevo_synced);
    }

    public function test_the_hourly_command_drains_the_pending_queue_once_configured(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['id' => 42], 201)]);
        $this->configure();

        $subscriber = $this->subscriber();

        $this->artisan('sharkweb:sync-newsletter')->assertExitCode(0);

        $this->assertTrue($subscriber->fresh()->brevo_synced);
    }
}
