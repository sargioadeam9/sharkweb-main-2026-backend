<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Content\CaseStudy;
use App\Models\Content\FeaturedCard;
use App\Models\Content\OssProject;
use App\Models\Content\PageHeader;
use App\Models\Content\Post;
use App\Models\Content\Review;
use App\Models\Content\Service;
use App\Models\Content\ServiceGroup;
use App\Models\Content\Stat;
use App\Models\Content\Tier;
use Illuminate\Http\JsonResponse;

/**
 * Read-only content for the six pages the owner edits in the panel.
 *
 * WHAT THIS DOES NOT DO: it does not build presentation structures. The frontend's
 * `caseReview()` and its `cardFor()` are pure functions over case data, and the 3D deck
 * card they produce is markup-specific (tilt, z-index, thumbnails). Reproducing that in
 * PHP would mean two implementations of the same layout, drifting apart on the first
 * design change. So this endpoint returns the *data* and the frontend keeps its
 * formatters.
 *
 * ONE ENDPOINT PER PAGE, not one per record. Each of these is a single request that
 * carries everything a page needs, which matters because the frontend is statically
 * generated: a page that made four round trips would make four cache entries to purge.
 *
 * `data` ENVELOPE. The frontend's `backendGet()` unwraps a `data` key when present, so
 * wrapping keeps these reads compatible with the same helper that serves everything else
 * and leaves room to add `meta` later without a breaking change.
 *
 * INACTIVE ROWS ARE EXCLUDED. `is_active` / `is_published` are the panel's visibility
 * switches, so a record the owner has hidden must not be served — otherwise "hide" would
 * only affect the pages that happened to filter it.
 */
class ContentController
{
    /* ================================================================== *
     * Services
     * ================================================================== */

    /**
     * The catalogue: groups with their services nested, plus every service page.
     *
     * BOTH SHAPES IN ONE CALL because the index and the detail pages share a cache tag. A
     * separate `/services/{slug}` call per page would mean thirteen cache entries to purge
     * when a discipline is renamed, and the index would still be stale.
     */
    public function services(): JsonResponse
    {
        $groups = ServiceGroup::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $byGroup = $services->groupBy('group_id');

        return response()->json([
            'data' => [
                'groups' => $groups->map(fn (ServiceGroup $group): array => $group->toFrontendArray(
                    ($byGroup[$group->id] ?? collect())
                        ->map(fn (Service $service): array => [
                            'slug' => $service->slug,
                            'name' => $service->name,
                            'desc' => $service->desc ?? '',
                            'tone' => $service->tone,
                        ])
                        ->values()
                        ->all(),
                ))->values()->all(),

                // Keyed by slug: the frontend's `SERVICES` is a Record, and a lookup by
                // slug is how every caller reads it.
                'pages' => $services->mapWithKeys(
                    fn (Service $service): array => [$service->slug => $this->servicePayload($service)],
                )->all(),
            ],
        ]);
    }

    public function service(string $slug): JsonResponse
    {
        $service = Service::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($service === null) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        return response()->json(['data' => $this->servicePayload($service)]);
    }

    /**
     * One service page, with its testimonial resolved from `review_case`.
     *
     * THE FALLBACK MATTERS. `review_case` is a slug, not a foreign key, so it can point at
     * a case the owner has since deleted or hidden. Rather than render a blank band, this
     * falls back to the first case that has a quote — the same rule the frontend's
     * `caseReview()` applies, kept in step deliberately.
     *
     * @return array<string, mixed>
     */
    private function servicePayload(Service $service): array
    {
        $case = $this->resolveReviewCase($service);
        $label = $service->review_label ?: '# TESTIMONIAL';

        // `reviews.items` is left empty on purpose: the band is built from `reviewCase`
        // below, and an empty list here means a client that ignores `reviewCase` renders
        // no band rather than a half-built one with no card.
        $payload = $service->toFrontendArray(['label' => $label, 'items' => []]);

        // The resolved case travels as data, not as a pre-built band. The frontend's
        // `caseReviewFor()` turns it into the quote, the headline and the 3D deck card —
        // structures with tilt, z-index and thumbnail rules that belong in the component,
        // not in a PHP array that would then need maintaining in parallel.
        $payload['reviewCase'] = $case?->toFrontendArray();
        $payload['reviewLabel'] = $label;

        return $payload;
    }

    /**
     * Which case a service's testimonial band quotes.
     *
     * Slug first, then the first quoted case as a fallback, then null. Kept as its own
     * method so `servicePayload()` does not resolve it twice.
     */
    private function resolveReviewCase(Service $service): ?CaseStudy
    {
        if ($service->review_case) {
            $case = CaseStudy::query()
                ->where('slug', $service->review_case)
                ->where('is_active', true)
                ->first();

            if ($case !== null) {
                return $case;
            }
        }

        return CaseStudy::query()
            ->where('is_active', true)
            ->whereNotNull('quote')
            ->orderBy('sort')
            ->first();
    }

    /* ================================================================== *
     * Newsletter
     * ================================================================== */

    /**
     * Every published issue, newest first.
     *
     * The body is included. It is the bulk of the payload (~200KB for five issues), but the
     * frontend generates the detail pages from the same array, and splitting it would mean
     * two cache entries per issue for no benefit at this size.
     */
    public function newsletter(): JsonResponse
    {
        $posts = Post::query()
            ->where('is_published', true)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'posts' => $posts->map(fn (Post $post): array => $post->toFrontendArray())->all(),
            ],
        ]);
    }

    public function newsletterPost(string $slug): JsonResponse
    {
        $post = Post::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if ($post === null) {
            return response()->json(['message' => 'Issue not found.'], 404);
        }

        return response()->json(['data' => $post->toFrontendArray()]);
    }

    /* ================================================================== *
     * Work
     * ================================================================== */

    /**
     * The /work board: the timeline rows and the case cards, plus the case pages.
     *
     * THE CARD SHAPE IS BUILT HERE, unlike the review item above. `toWorkCardArray()` is a
     * straight restatement of case fields with no layout in it — the only reason it is not
     * on the case itself is that the border class depends on the card's index, which is a
     * property of the list rather than of the case.
     */
    public function work(): JsonResponse
    {
        $cases = CaseStudy::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->values();

        $edges = [
            'border-b border-line sm:border-r sm:border-line',
            'border-b border-line sm:border-r sm:border-line lg:border-r lg:border-line',
            'border-b border-line',
            'border-b border-line sm:border-r sm:border-line lg:border-r lg:border-line',
            'border-b border-line',
        ];

        return response()->json([
            'data' => [
                'cards' => $cases->map(
                    fn (CaseStudy $case, int $i): array => $case->toWorkCardArray($i, $edges[$i % count($edges)]),
                )->all(),

                'cases' => $cases->map(fn (CaseStudy $case): array => $case->toFrontendArray())->all(),
            ],
        ]);
    }

    public function workCase(string $slug): JsonResponse
    {
        $case = CaseStudy::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($case === null) {
            return response()->json(['message' => 'Case study not found.'], 404);
        }

        return response()->json(['data' => $case->toFrontendArray()]);
    }

    /* ================================================================== *
     * Open source
     * ================================================================== */

    public function openSource(): JsonResponse
    {
        $projects = OssProject::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'projects' => $projects->map(fn (OssProject $project): array => $project->toFrontendArray())->all(),
            ],
        ]);
    }

    /* ================================================================== *
     * Commitment tiers
     * ================================================================== */

    public function tiers(): JsonResponse
    {
        $tiers = Tier::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'tiers' => $tiers->map(fn (Tier $tier): array => $tier->toFrontendArray())->all(),
            ],
        ]);
    }


    /* ================================================================== *
     * Homepage bands
     * ================================================================== */

    /**
     * The five figures under the hero.
     *
     * An empty list is a valid answer: the frontend falls back to its bundled copy only
     * when the request fails, so returning `[]` here means the owner really did switch
     * every stat off and the bar should render empty rather than resurrect old numbers.
     */
    public function stats(): JsonResponse
    {
        $stats = Stat::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'stats' => $stats->map(fn (Stat $stat): array => $stat->toFrontendArray())->all(),
            ],
        ]);
    }

    /**
     * The featured projects deck.
     *
     * The index is passed in because the card's tilt, shift and z-order are derived from
     * its position in the list, not stored — see `App\Support\DeckCard`.
     */
    public function featured(): JsonResponse
    {
        $cards = FeaturedCard::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->values();

        return response()->json([
            'data' => [
                'featured' => $cards
                    ->map(fn (FeaturedCard $card, int $i): array => $card->toFrontendArray($i))
                    ->all(),
            ],
        ]);
    }

    /**
     * The client review carousel, each with its card composed from position.
     */
    public function reviews(): JsonResponse
    {
        $reviews = Review::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->values();

        return response()->json([
            'data' => [
                'reviews' => $reviews
                    ->map(fn (Review $review, int $i): array => $review->toFrontendArray($i))
                    ->all(),
            ],
        ]);
    }

    /* ================================================================== *
     * Page copy
     * ================================================================== */

    /**
     * The hero, sidebar card and closing band for the four routes, keyed by route.
     *
     * Keyed rather than listed because the frontend asks for one route at a time by name —
     * `headers.services`, not `headers.find(h => h.route === 'services')`.
     */
    public function pageHeaders(): JsonResponse
    {
        $headers = PageHeader::query()->orderBy('route')->get();

        return response()->json([
            'data' => [
                'headers' => $headers->mapWithKeys(
                    fn (PageHeader $header): array => [$header->route => $header->toFrontendArray()],
                )->all(),
            ],
        ]);
    }
}
