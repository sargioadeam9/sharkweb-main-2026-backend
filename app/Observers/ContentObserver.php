<?php

declare(strict_types=1);

namespace App\Observers;

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
use App\Services\IsrWebhookService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One observer for the whole content module.
 *
 * WHY ONE CLASS AND NOT SEVEN. Every content model needs the same three things: which
 * cache tags to purge, which paths to rebuild, and a guarantee that a purge failure never
 * breaks the editor's save. Seven observers would be seven copies of the same try/catch
 * and seven chances to forget a path.
 *
 * WHY THE PATH LIST IS PER MODEL, NOT GLOBAL. Purging everything on every save is the safe
 * option and the wrong one: a site with thirteen service pages, five cases and five issues
 * would rebuild all of them because somebody fixed a typo in one FAQ. The point of the
 * webhook is that it is targeted.
 *
 * THE OLD SLUG IS PURGED TOO. Renaming a service from `ppc` to `paid-search` leaves the
 * old URL cached and still serving a page that no longer exists in the catalogue. `saved`
 * fires after the change, so the old value is recovered from `getOriginal()`. Same reason
 * the frontend's `revalidatePath` takes explicit paths rather than deriving them.
 *
 * NEVER THROWS. This runs inside an admin's save request. A frontend that is down, slow or
 * misconfigured must not stop an editor from saving their work — that is the whole reason
 * `IsrWebhookService` fails soft, and this observer must not undo it by throwing on a null.
 */
class ContentObserver
{
    public function __construct(private readonly IsrWebhookService $isr) {}

    public function saved(Model $model): void
    {
        $this->purge($model, 'content.saved', previousSlug: $this->previousSlug($model));
    }

    public function deleted(Model $model): void
    {
        $this->purge($model, 'content.deleted');
    }

    /** A restored row has to come back on the site, so it is a save in every way that matters. */
    public function restored(Model $model): void
    {
        $this->purge($model, 'content.restored');
    }

    /**
     * The slug as it was before this save, when it changed.
     *
     * `getOriginal()` reads the synced original, which Eloquent populates after a save —
     * so on `saved` this is the pre-save value. On `created` there is no previous value and
     * this returns null, which `pathsFor()` ignores.
     */
    private function previousSlug(Model $model): ?string
    {
        try {
            if (! $model->wasChanged('slug')) {
                return null;
            }

            $old = $model->getOriginal('slug');

            return is_string($old) && $old !== '' ? $old : null;
        } catch (Throwable) {
            // No slug column, or an original that cannot be read. Not worth a warning.
            return null;
        }
    }

    private function purge(Model $model, string $event, ?string $previousSlug = null): void
    {
        try {
            [$tags, $paths] = $this->targetsFor($model);

            if ($previousSlug !== null) {
                $paths = [...$paths, ...$this->slugPaths($model, $previousSlug)];
            }

            $this->isr->revalidate($paths, $tags, $event);
        } catch (Throwable $e) {
            // A save must succeed even if the purge cannot be worked out.
            Log::warning('Content revalidation skipped.', [
                'model' => $model::class,
                'key' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cache tags and page paths a change to this record invalidates.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function targetsFor(Model $model): array
    {
        return match (true) {
            $model instanceof Service => [['services'], $this->slugPaths($model, $model->slug)],
            $model instanceof ServiceGroup => [['services'], $this->groupPaths($model)],
            $model instanceof Post => [['posts'], $this->slugPaths($model, $model->slug)],
            $model instanceof CaseStudy => $this->caseTargets($model),
            $model instanceof OssProject => [['oss-projects'], ['/open-source/']],
            $model instanceof Tier => [['tiers'], ['/services/']],
            $model instanceof PageHeader => [['page-copy'], $this->headerPaths($model)],
            // All three homepage bands live on `/` only, so the purge is one path each.
            $model instanceof Stat => [['stats'], ['/']],
            $model instanceof FeaturedCard => [['featured'], ['/']],
            $model instanceof Review => [['reviews'], ['/']],
            default => [[], []],
        };
    }

    /**
     * The index plus the record's own page, for the two models whose URL is `/x/{slug}/`.
     *
     * The index is always included: a new record has to appear in the list, and a deleted
     * one has to disappear from it. Purging only the detail page would leave the list
     * showing the old set until the 300-second window lapsed.
     *
     * @return list<string>
     */
    private function slugPaths(Model $model, ?string $slug): array
    {
        $base = match (true) {
            $model instanceof Service => '/services/',
            $model instanceof Post => '/newsletter/',
            $model instanceof CaseStudy => '/work/',
            default => null,
        };

        if ($base === null) {
            return [];
        }

        $paths = [$base];

        if ($slug !== null && $slug !== '') {
            $paths[] = "{$base}{$slug}/";
        }

        return $paths;
    }

    /**
     * A discipline change reorders the catalogue, so the index AND every service page in
     * the group is affected — the group's name is printed on each one.
     *
     * @return list<string>
     */
    private function groupPaths(ServiceGroup $group): array
    {
        $paths = ['/services/'];

        foreach ($group->services()->pluck('slug') as $slug) {
            if (is_string($slug) && $slug !== '') {
                $paths[] = "/services/{$slug}/";
            }
        }

        return $paths;
    }

    /**
     * A case study is the single source of the review cards, so it reaches further than
     * its own page: the /work board, the case page, and every service page that quotes it.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function caseTargets(CaseStudy $case): array
    {
        return [
            // `reviews` is the tag the review bands read; `case-studies` is the /work board.
            ['case-studies', 'reviews', 'services'],
            [
                '/work/',
                ...($case->slug ? ["/work/{$case->slug}/"] : []),
                '/services/',
            ],
        ];
    }

    /**
     * A page header belongs to exactly one route, and the route is the lookup.
     *
     * @return list<string>
     */
    private function headerPaths(PageHeader $header): array
    {
        return match ($header->route) {
            'services' => ['/services/'],
            'newsletter' => ['/newsletter/'],
            'work' => ['/work/'],
            'open-source' => ['/open-source/'],
            default => [],
        };
    }
}
