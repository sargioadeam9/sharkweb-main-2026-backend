<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The endpoints the public site actually calls.
 *
 * WHY THIS FILE EXISTS. The contact form was returning its generic "something went wrong"
 * message on every submission, and the suite was green the whole time — because nothing
 * posted to `/api/v1/leads`. The cause was a constructor argument left behind by the
 * analytics purge: `LeadService` asked the container for two services that no longer
 * existed, so resolution threw before any lead logic ran. A single request in a test would
 * have caught it the moment the classes were deleted.
 *
 * So these tests are deliberately end-to-end: real HTTP, real middleware, real service
 * container. Asserting on `LeadService` in isolation would have passed while the route
 * 500'd.
 */
class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = 'test-api-key-'.str_repeat('x', 12);
        config(['sharkweb.api_key' => $this->key]);
    }

    /** @return array<string, string> */
    private function auth(): array
    {
        return [
            'Authorization' => "Bearer {$this->key}",
            'Accept' => 'application/json',
        ];
    }

    /* ================================================================== *
     * Leads — the brief form
     * ================================================================== */

    public function test_a_brief_is_accepted_and_stored(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/leads', [
                'name' => 'Zonaed Hossain',
                'email' => 'zon@example.com',
                'goal' => 'NEW BUILD',
                'message' => 'We need a new storefront.',
                'ip_address' => '203.0.113.10',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('leads', [
            'email' => 'zon@example.com',
            'goal' => 'NEW BUILD',
        ]);
    }


    /**
     * The conversion id has to come back to the browser.
     *
     * The pixel reports `Lead` with this id, and the Conversions API will report the same
     * id from the server. Meta deduplicates on it — if the two sides invent their own, one
     * lead is counted twice the day server-side tracking is switched on.
     */
    public function test_a_brief_returns_the_event_id_the_pixel_needs(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/leads', [
                'name' => 'Zonaed Hossain',
                'email' => 'zon@example.com',
                'goal' => 'NEW BUILD',
                'message' => 'We need a new storefront.',
                'ip_address' => '203.0.113.10',
            ]);

        $response->assertCreated();

        $eventId = $response->json('event_id');

        $this->assertIsString($eventId);
        $this->assertNotSame('', $eventId);

        // The id handed to the browser is the one stored on the lead, not a fresh one.
        $this->assertDatabaseHas('leads', [
            'email' => 'zon@example.com',
            'event_id' => $eventId,
        ]);
    }

    public function test_a_brief_stores_the_optional_phone_number(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/leads', [
                'name' => 'Zonaed Hossain',
                'email' => 'zon@example.com',
                'phone' => '+880 1712 345678',
                'goal' => 'NEW BUILD',
                'message' => 'We need a new storefront.',
                'ip_address' => '203.0.113.10',
            ])
            ->assertCreated();

        $lead = \App\Models\Lead::query()->where('email', 'zon@example.com')->firstOrFail();

        $this->assertNotNull($lead->phone, 'The phone number is what advanced matching uses.');
    }


    /**
     * Campaign parameters survive the trip.
     *
     * These columns existed from the beginning and were never populated, so the panel's
     * "Lead source" chart bucketed every lead as "direct / none". The frontend now captures
     * them on landing and sends them with the brief; this is the contract on the other end.
     */
    public function test_a_brief_stores_the_campaign_attribution(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/leads', [
                'name' => 'Zonaed Hossain',
                'email' => 'zon@example.com',
                'goal' => 'NEW BUILD',
                'message' => 'We need a new storefront.',
                'ip_address' => '203.0.113.10',
                'utm_source' => 'facebook',
                'utm_medium' => 'paid_social',
                'utm_campaign' => 'meta_ads_october',
                'utm_term' => 'shopify agency',
                'utm_content' => 'carousel_b',
                'referrer_url' => 'https://www.facebook.com/',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('leads', [
            'email' => 'zon@example.com',
            'utm_source' => 'facebook',
            'utm_medium' => 'paid_social',
            'utm_campaign' => 'meta_ads_october',
            'utm_term' => 'shopify agency',
            'utm_content' => 'carousel_b',
            'referrer_url' => 'https://www.facebook.com/',
        ]);
    }

    /**
     * The exact failure that broke the form: resolving the controller's dependencies must
     * not throw. A 500 here is what the visitor saw as "something went wrong".
     */
    public function test_the_brief_endpoint_does_not_throw_on_resolution(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/leads', [
                'name' => 'Resolution Check',
                'email' => 'resolve@example.com',
                'message' => 'Can the container build the service?',
            ]);

        $this->assertNotSame(500, $response->status(), 'The lead service could not be resolved.');
        $this->assertSame(201, $response->status());
    }

    public function test_a_brief_without_a_name_is_rejected(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/leads', ['email' => 'noname@example.com'])
            ->assertStatus(422);
    }

    public function test_a_brief_without_the_api_key_is_refused(): void
    {
        $this->postJson('/api/v1/leads', [
            'name' => 'Anonymous',
            'email' => 'anon@example.com',
        ])->assertUnauthorized();
    }

    /** An unconfigured key must fail closed, not open. */
    public function test_an_unconfigured_api_key_refuses_every_request(): void
    {
        config(['sharkweb.api_key' => null]);

        $this->withHeaders(['Authorization' => 'Bearer anything'])
            ->postJson('/api/v1/leads', ['name' => 'X', 'email' => 'x@example.com'])
            ->assertStatus(503);
    }

    /* ================================================================== *
     * Newsletter — the subscribe form
     * ================================================================== */

    public function test_a_subscriber_is_accepted(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/newsletter', ['email' => 'reader@example.com']);

        $this->assertContains($response->status(), [200, 201, 202]);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'reader@example.com']);
    }

    /* ================================================================== *
     * Content reads — what the pages render from
     * ================================================================== */

    /**
     * @return array<string, array{0: string}>
     */
    public static function contentEndpointProvider(): array
    {
        return [
            'services' => ['/api/v1/content/services'],
            'newsletter' => ['/api/v1/content/newsletter'],
            'work' => ['/api/v1/content/work'],
            'open source' => ['/api/v1/content/open-source'],
            'tiers' => ['/api/v1/content/tiers'],
            'page copy' => ['/api/v1/content/page-headers'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('contentEndpointProvider')]
    public function test_a_content_endpoint_answers(string $url): void
    {
        $this->withHeaders($this->auth())
            ->getJson($url)
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_content_reads_require_the_api_key(): void
    {
        $this->getJson('/api/v1/content/tiers')->assertUnauthorized();
    }

    /** The health check is deliberately outside the auth group, for uptime monitors. */
    public function test_health_is_reachable_without_a_key(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
    }

    /* ================================================================== *
     * Cleanup between cases
     * ================================================================== */

    protected function tearDown(): void
    {
        // The rate limiter is keyed per IP and persists across tests in the same process,
        // so a run of submissions would otherwise start returning 429 partway through.
        $this->app['cache']->flush();

        parent::tearDown();
    }
}
