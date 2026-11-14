<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Content\CaseStudy;
use App\Models\Content\OssProject;
use App\Models\Content\PageHeader;
use App\Models\Content\Post;
use App\Models\Content\Service;
use App\Models\Content\ServiceGroup;
use App\Models\Content\Tier;
use App\Services\IsrWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * What a save in the panel tells the frontend to rebuild.
 *
 * THE POINT OF THESE TESTS IS THE PATH LIST. `ContentObserver` decides which pages go
 * stale when a record changes, and every failure mode here is silent in production: too
 * few paths and the site serves yesterday's copy until the 300-second window lapses, too
 * many and one FAQ typo rebuilds thirteen pages. Neither throws, neither logs, and neither
 * shows up in a browser — the only symptom is a page that is subtly out of date.
 *
 * The service is mocked rather than called, because what is under test is the mapping, not
 * the HTTP. `IsrWebhookService` has its own failure handling and is deliberately inert when
 * `ISR_ENABLED` is false, which it is in the test environment.
 */
class ContentRevalidationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{paths: list<string>, tags: list<string>, event: string}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Record every call instead of making it. Returning true keeps the observer on its
        // success path, which is the one an editor's save takes.
        $spy = Mockery::mock(IsrWebhookService::class);
        $spy->shouldReceive('revalidate')
            ->andReturnUsing(function (array $paths = [], array $tags = [], string $event = ''): bool {
                $this->calls[] = ['paths' => $paths, 'tags' => $tags, 'event' => $event];

                return true;
            });

        $this->app->instance(IsrWebhookService::class, $spy);
    }

    /** @return array{paths: list<string>, tags: list<string>, event: string} */
    private function lastCall(): array
    {
        $this->assertNotEmpty($this->calls, 'The observer did not ask for a revalidation at all.');

        return $this->calls[array_key_last($this->calls)];
    }

    private function service(string $slug = 'meta-ads'): Service
    {
        $group = ServiceGroup::create([
            'num' => '01',
            'key' => 'growth-media',
            'name' => 'Growth & media',
            'tone' => 'accent',
            'sort' => 0,
        ]);

        return Service::create([
            'slug' => $slug,
            'group_id' => $group->id,
            'name' => 'Meta Ads',
            'desc' => 'Paid social',
            'tone' => 'accent',
            'sort' => 0,
        ]);
    }

    public function test_a_service_save_purges_the_index_and_its_own_page(): void
    {
        $service = $this->service();
        $this->calls = [];

        $service->update(['h1' => 'Meta Ads, paid social']);

        $call = $this->lastCall();

        $this->assertSame(['services'], $call['tags']);
        $this->assertContains('/services/', $call['paths']);
        $this->assertContains('/services/meta-ads/', $call['paths']);
    }

    /**
     * The old URL has to go too. Without it, renaming a service leaves the previous page
     * cached and reachable at an address the catalogue no longer lists.
     */
    public function test_renaming_a_service_purges_the_old_slug_as_well(): void
    {
        $service = $this->service('ppc');
        $this->calls = [];

        $service->update(['slug' => 'paid-search']);

        $paths = $this->lastCall()['paths'];

        $this->assertContains('/services/paid-search/', $paths, 'The new page was not purged.');
        $this->assertContains('/services/ppc/', $paths, 'The old page was left cached.');
    }

    public function test_a_discipline_save_purges_every_service_in_it(): void
    {
        $group = ServiceGroup::create([
            'num' => '01',
            'key' => 'growth-media',
            'name' => 'Growth & media',
            'tone' => 'accent',
            'sort' => 0,
        ]);

        foreach (['meta-ads', 'ppc'] as $i => $slug) {
            Service::create([
                'slug' => $slug,
                'group_id' => $group->id,
                'name' => ucfirst($slug),
                'tone' => 'accent',
                'sort' => $i,
            ]);
        }

        $this->calls = [];
        $group->update(['name' => 'Growth and media']);

        $paths = $this->lastCall()['paths'];

        $this->assertContains('/services/', $paths);
        $this->assertContains('/services/meta-ads/', $paths, 'A grouped service page was left stale.');
        $this->assertContains('/services/ppc/', $paths, 'A grouped service page was left stale.');
    }

    public function test_a_newsletter_save_purges_the_archive_and_the_issue(): void
    {
        $post = Post::create([
            'slug' => 'core-web-vitals-guide',
            'title' => 'Core Web Vitals',
            'category' => 'Performance',
            'tone' => 'accent',
            'date' => now(),
            'read_time' => 6,
            'is_published' => true,
        ]);

        $this->calls = [];
        $post->update(['title' => 'Core Web Vitals, explained']);

        $call = $this->lastCall();

        $this->assertSame(['posts'], $call['tags']);
        $this->assertContains('/newsletter/', $call['paths']);
        $this->assertContains('/newsletter/core-web-vitals-guide/', $call['paths']);
    }

    /**
     * A case study reaches three surfaces: the board, its own page, and the testimonial
     * bands on the service pages that quote it.
     */
    public function test_a_case_save_also_purges_the_service_pages_that_quote_it(): void
    {
        $case = CaseStudy::create([
            'slug' => 'aryia-leather',
            'client' => 'Aryia Leather Co.',
            'vertical' => 'eCommerce',
            'tone' => 'orange',
            'sort' => 0,
            'quote' => ['text' => 'Great work.', 'name' => 'James', 'role' => 'Founder'],
        ]);

        $this->calls = [];
        $case->update(['summary' => 'A rebuilt paid media architecture.']);

        $call = $this->lastCall();

        $this->assertContains('case-studies', $call['tags']);
        $this->assertContains('reviews', $call['tags'], 'The review bands would go stale.');
        $this->assertContains('services', $call['tags'], 'The service pages would go stale.');

        $this->assertContains('/work/', $call['paths']);
        $this->assertContains('/work/aryia-leather/', $call['paths']);
        $this->assertContains('/services/', $call['paths']);
    }

    public function test_an_open_source_tile_purges_only_the_open_source_page(): void
    {
        $project = OssProject::create([
            'num' => 'OSS.01',
            'name' => 'GITiempo',
            'href' => '/gitiempo/',
            'tagline' => 'Time tracking',
            'year' => '2026',
            'sort' => 0,
        ]);

        $this->calls = [];
        $project->update(['tagline' => 'Time tracking, native to GitHub']);

        $call = $this->lastCall();

        $this->assertSame(['oss-projects'], $call['tags']);
        $this->assertSame(['/open-source/'], $call['paths']);
    }

    public function test_a_tier_save_purges_the_services_index(): void
    {
        $tier = Tier::create([
            'name' => 'Growth sprint',
            'price' => 'from $1.5k',
            'cta' => 'PLAN A SPRINT',
            'href' => '/contact/',
            'sort' => 0,
        ]);

        $this->calls = [];
        $tier->update(['price' => 'from $1.8k']);

        $call = $this->lastCall();

        $this->assertSame(['tiers'], $call['tags']);
        $this->assertSame(['/services/'], $call['paths']);
    }

    public function test_a_page_header_purges_the_route_it_belongs_to(): void
    {
        $header = PageHeader::create([
            'route' => 'work',
            'kicker' => '# WORK',
            'title' => 'Selected work',
            'lead' => 'Five engagements.',
        ]);

        $this->calls = [];
        $header->update(['title' => 'Selected engagements']);

        $call = $this->lastCall();

        $this->assertSame(['page-copy'], $call['tags']);
        $this->assertSame(['/work/'], $call['paths']);
    }

    /**
     * The grid's border classes are a function of position, so a reorder has to rewrite
     * them. Filament's drag-and-drop updates `sort` directly, bypassing the form — which is
     * exactly why this lives in the model's `saving` hook rather than a page mutator.
     */
    public function test_reordering_a_tile_rewrites_its_grid_classes(): void
    {
        $first = OssProject::create([
            'num' => 'OSS.01',
            'name' => 'First',
            'href' => '/a/',
            'year' => '2026',
            'sort' => 0,
        ]);

        $this->assertStringContainsString('sm:border-r', $first->cell);

        // Position 2 in the three-column grid has no right border.
        $first->update(['sort' => 2]);

        $this->assertSame(OssProject::cellFromSort(2), $first->fresh()->cell);
    }

    /**
     * A purge that cannot be worked out must not stop the save.
     *
     * This is the guarantee the whole observer exists to preserve: the frontend being down,
     * or a model arriving without a slug, is an editor's problem to notice later — not a
     * reason for the save button to fail.
     */
    public function test_a_failing_purge_does_not_break_the_save(): void
    {
        $failing = Mockery::mock(IsrWebhookService::class);
        $failing->shouldReceive('revalidate')->andThrow(new \RuntimeException('frontend is down'));
        $this->app->instance(IsrWebhookService::class, $failing);

        $tier = Tier::create([
            'name' => 'Micro',
            'price' => 'from $500',
            'cta' => 'START',
            'href' => '/contact/',
            'sort' => 0,
        ]);

        $tier->update(['price' => 'from $650']);

        $this->assertSame('from $650', $tier->fresh()->price);
    }
}
