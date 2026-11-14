<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Content\FeaturedCard;
use App\Models\Content\Review;
use App\Models\Content\Stat;
use App\Support\DeckCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three homepage bands that used to be hardcoded in TypeScript.
 *
 * WHAT THESE TESTS PROTECT. The whole point of moving this content into the database was
 * that nothing about the page should change, so the assertions are about the composed
 * output rather than about storage: a card's tilt and z-order must come from its position,
 * a review's card must stay flat, and a tone must be emitted bare because the components
 * wrap it in `text()` themselves. Getting any of those wrong renders a visually different
 * page from data that looks correct in the panel.
 */
class HomepageModulesTest extends TestCase
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
     * Stats
     * ================================================================== */

    public function test_the_stats_endpoint_returns_the_active_figures_in_order(): void
    {
        Stat::query()->create(['label' => 'second', 'value' => '2', 'suffix' => '', 'caption' => '', 'tone' => 'green', 'is_active' => true, 'sort' => 2]);
        Stat::query()->create(['label' => 'first', 'value' => '1', 'suffix' => '+', 'caption' => 'one', 'tone' => 'ink', 'is_active' => true, 'sort' => 1]);
        Stat::query()->create(['label' => 'hidden', 'value' => '9', 'suffix' => '', 'caption' => '', 'tone' => 'ink', 'is_active' => false, 'sort' => 0]);

        $response = $this->withHeaders($this->auth())->getJson('/api/v1/content/stats');

        $response->assertOk();

        $stats = $response->json('data.stats');

        $this->assertCount(2, $stats, 'The inactive figure must not be published.');
        $this->assertSame(['first', 'second'], array_column($stats, 'label'));
        $this->assertSame('text-ink', $stats[0]['tone'], 'The tone is stored bare and published as a class.');
    }

    public function test_the_stats_endpoint_requires_the_api_key(): void
    {
        $this->getJson('/api/v1/content/stats')->assertUnauthorized();
    }

    /* ================================================================== *
     * Featured projects
     * ================================================================== */

    public function test_a_featured_card_takes_its_geometry_from_its_position(): void
    {
        foreach (['A', 'B', 'C'] as $i => $name) {
            FeaturedCard::query()->create([
                'name' => $name,
                'domain' => 'TEST',
                'tone' => 'green',
                'front_bg' => 'green-soft',
                'is_active' => true,
                'sort' => $i,
            ]);
        }

        $cards = $this->withHeaders($this->auth())
            ->getJson('/api/v1/content/featured')
            ->assertOk()
            ->json('data.featured');

        $this->assertCount(3, $cards);

        // Position decides the pile: index, z-order and the tilt/shift pair.
        $this->assertSame([0, 1, 2], array_column($cards, 'i'));
        $this->assertSame([1, 2, 3], array_column($cards, 'z'));
        $this->assertSame(['-3.4deg', '2.6deg', '-1.8deg'], array_column($cards, 'tilt'));

        // Tones are bare so the component's text() helper can map them to classes.
        $this->assertSame('green', $cards[0]['brandTone']);
        $this->assertSame('green', $cards[0]['ctaTone']);
        $this->assertSame('green-soft', $cards[0]['frontBg']);
    }

    public function test_a_featured_card_without_a_number_falls_back_to_its_position(): void
    {
        FeaturedCard::query()->create(['name' => 'A', 'tone' => 'ink', 'is_active' => true, 'sort' => 0]);

        $card = $this->withHeaders($this->auth())
            ->getJson('/api/v1/content/featured')
            ->json('data.featured.0');

        $this->assertSame('# 001', $card['backBrand']);
    }

    public function test_a_saved_card_number_is_used_verbatim(): void
    {
        FeaturedCard::query()->create(['name' => 'A', 'tone' => 'ink', 'back_brand' => '# 032', 'is_active' => true, 'sort' => 0]);

        $card = $this->withHeaders($this->auth())
            ->getJson('/api/v1/content/featured')
            ->json('data.featured.0');

        $this->assertSame('# 032', $card['backBrand'], 'The shipped cards reference project numbers, not list positions.');
    }

    /* ================================================================== *
     * Reviews
     * ================================================================== */

    public function test_a_review_card_is_flat_however_far_down_the_list_it_sits(): void
    {
        foreach (['A', 'B', 'C'] as $i => $title) {
            Review::query()->create([
                'title' => $title,
                'quote' => 'quote',
                'name' => 'Reviewer '.$i,
                'card_name' => 'Project '.$i,
                'card_tone' => 'accent',
                'is_active' => true,
                'sort' => $i,
            ]);
        }

        $reviews = $this->withHeaders($this->auth())
            ->getJson('/api/v1/content/reviews')
            ->assertOk()
            ->json('data.reviews');

        // The carousel shows one card at a time, so unlike the featured deck every card
        // sits at index 0, z 1, with no tilt or shift.
        foreach ($reviews as $review) {
            $this->assertSame(0, $review['card']['i']);
            $this->assertSame(1, $review['card']['z']);
            $this->assertSame('0deg', $review['card']['tilt']);
            $this->assertSame('0%', $review['card']['shift']);
        }
    }

    public function test_the_card_names_the_project_not_the_reviewer(): void
    {
        Review::query()->create([
            'title' => 'A review',
            'quote' => 'quote',
            'name' => 'Elena Pomazanova',
            'card_name' => 'Retail Network',
            'is_active' => true,
            'sort' => 0,
        ]);

        $review = $this->withHeaders($this->auth())
            ->getJson('/api/v1/content/reviews')
            ->json('data.reviews.0');

        $this->assertSame('Retail Network', $review['card']['name']);
        $this->assertSame('Elena Pomazanova', $review['name']);
    }

    /* ================================================================== *
     * DeckCard
     * ================================================================== */

    public function test_the_spec_table_is_stored_as_rows_and_published_as_pairs(): void
    {
        $card = DeckCard::compose([
            'name' => 'X',
            'specs' => [
                ['label' => 'domain', 'value' => 'Retail'],
                ['label' => 'client', 'value' => 'under NDA'],
            ],
        ], 0);

        $this->assertSame([['domain', 'Retail'], ['client', 'under NDA']], $card['specs']);
    }

    public function test_the_spec_table_still_accepts_the_older_pair_shape(): void
    {
        $card = DeckCard::compose([
            'name' => 'X',
            'specs' => [['domain', 'Retail']],
        ], 0);

        $this->assertSame([['domain', 'Retail']], $card['specs']);
    }

    public function test_an_unknown_tone_falls_back_rather_than_emitting_a_broken_class(): void
    {
        $card = DeckCard::compose(['name' => 'X', 'tone' => 'chartreuse'], 0);

        $this->assertSame('accent', $card['brandTone']);
    }

    /* ================================================================== *
     * Importer
     * ================================================================== */

    public function test_the_homepage_importer_is_idempotent(): void
    {
        if (! file_exists(database_path('content/homepage.json'))) {
            $this->markTestSkipped('No database/content/homepage.json — run .ref/dump-homepage.mjs first.');
        }

        $this->artisan('sharkweb:import-content --only=stats')->assertSuccessful();
        $this->artisan('sharkweb:import-content --only=stats')->assertSuccessful();

        $this->assertSame(5, Stat::query()->count(), 'A second run must update in place, not append.');
    }
}
