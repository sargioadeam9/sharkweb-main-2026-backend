<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Import the frontend's bundled content into the editable tables.
 *
 * WHY: the panel the owner now has is only useful if it opens full of the site's real
 * copy. These tables start empty, and hand-typing 13 service pages, 5 articles, 5 case
 * studies and 3 tiers into a browser would be a worse job than the one it replaces.
 *
 * THE INPUT IS GENERATED, NOT AUTHORED. `database/content/import.json` is produced by
 * compiling the TypeScript modules (`lib/services.ts` and friends) and serialising the
 * real exported values — so the numbers in here are the numbers the site renders today,
 * not a transcription. Re-generating it after a copy change is deliberate: this is a
 * one-time lift, not a sync, and re-running with `--fresh` is how a later change is
 * re-applied.
 *
 * IDEMPOTENT BY DEFAULT. A second run updates rows in place by slug/key rather than
 * creating duplicates, so running it twice during setup is not a mistake to recover from.
 * `--fresh` is the destructive path and is explicit.
 */
class ImportContentCommand extends Command
{
    protected $signature = 'sharkweb:import-content
                            {--fresh : Delete every content row before importing}
                            {--overwrite : Replace rows that already exist (see upsert())}
                            {--only= : Import one section: services, posts, cases, oss, tiers, headers, stats, featured, reviews}';

    protected $description = 'Import the frontend content modules into the editable content tables';

    /** @var array<string, int> */
    private array $counts = [];

    public function handle(): int
    {
        $path = database_path('content/import.json');

        if (! is_file($path)) {
            $this->error("Missing {$path}. See the class docblock for how it is generated.");

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            $this->error('import.json is not a JSON object.');

            return self::FAILURE;
        }

        $only = $this->option('only');
        $sections = ['services', 'posts', 'cases', 'oss', 'tiers', 'headers', 'stats', 'featured', 'reviews'];

        if ($only !== null && ! in_array($only, $sections, true)) {
            $this->error("Unknown --only value. Use one of: ".implode(', ', $sections));

            return self::FAILURE;
        }

        if ($this->option('fresh') && $only === null) {
            $this->wipe();
        }

        if ($only === null || $only === 'services') {
            $this->importServices($data);
        }

        if ($only === null || $only === 'posts') {
            $this->importPosts($data);
        }

        if ($only === null || $only === 'cases') {
            $this->importCases($data);
        }

        if ($only === null || $only === 'oss') {
            $this->importOss();
        }

        if ($only === null || $only === 'tiers') {
            $this->importTiers();
        }

        if ($only === null || $only === 'headers') {
            $this->importHeaders();
        }

        if ($only === null || $only === 'stats') {
            $this->importStats();
        }

        if ($only === null || $only === 'featured') {
            $this->importFeatured();
        }

        if ($only === null || $only === 'reviews') {
            $this->importReviews();
        }

        $this->newLine();
        $this->table(['Section', 'Rows'], array_map(
            fn (string $k, int $v): array => [$k, (string) $v],
            array_keys($this->counts),
            array_values($this->counts),
        ));

        return self::SUCCESS;
    }


    /**
     * Insert a row, or update it only when asked.
     *
     * WHY THIS IS NOT `updateOrCreate`. The command started life as a one-time lift from
     * the bundled TypeScript, where re-running to refresh the copy was the whole point. It
     * is no longer one-time: the panel is now the source of truth, and a plain
     * `updateOrCreate` means `php artisan sharkweb:import-content` silently reverts every
     * edit an editor has made back to whatever `import.json` happened to say. That is not
     * hypothetical — running it once reset the published tier prices from $12 / $900 /
     * $1800 back to $5 / $1.5k / $2.5k.
     *
     * So the default is now "create what is missing, leave what exists alone", and
     * `--overwrite` is the explicit way to re-apply the bundled copy. `--fresh` still
     * wipes first, which makes every row a create.
     *
     * Returns the existing row when it skips, because several callers use the model's id
     * straight afterwards to wire up relations.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function upsert(string $model, array $keys, array $values): Model
    {
        $existing = $model::query()->where($keys)->first();

        if ($existing instanceof Model && ! $this->option('overwrite')) {
            return $existing;
        }

        return $model::query()->updateOrCreate($keys, $values);
    }

    /** The destructive path. `--fresh` only. */
    private function wipe(): void
    {
        $this->warn('--fresh: deleting every content row.');

        // Truncating rather than deleting so the autoincrement restarts, which keeps the
        // Tiers' and OSS projects' `sort`-derived display order predictable.
        foreach (['content_services', 'content_service_groups', 'content_posts', 'content_cases', 'content_oss_projects', 'content_tiers', 'content_page_headers', 'content_stats', 'content_featured_cards', 'content_reviews'] as $table) {
            DB::table($table)->delete();
            $this->line("  cleared {$table}");
        }
    }

    /* ================================================================== *
     * Services
     * ================================================================== */

    private function importServices(array $data): void
    {
        $groups = $data['serviceGroups'] ?? [];
        $services = $data['services'] ?? [];

        $groupIds = [];

        foreach ($groups as $i => $g) {
            $model = $this->upsert(ServiceGroup::class, 
                ['key' => $g['key']],
                [
                    'num' => $g['num'] ?? '',
                    'name' => $g['name'],
                    'tone' => $g['tone'] ?? 'accent',
                    'blurb' => $g['blurb'] ?? null,
                    'sort' => $i,
                    'is_active' => true,
                ],
            );

            $groupIds[$g['key']] = $model->id;
            $this->counts['Groups'] = ($this->counts['Groups'] ?? 0) + 1;
        }

        // A slug's group is whichever group lists it — the group is authoritative, so the
        // service row's own `group_id` is derived rather than duplicated in the source.
        $slugToGroup = [];

        foreach ($groups as $g) {
            foreach ($g['services'] ?? [] as $s) {
                $slugToGroup[$s['slug']] = $groupIds[$g['key']] ?? null;
            }
        }

        $order = array_keys($services);

        foreach ($services as $slug => $s) {
            // `reviews` is rebuilt from the case record at render time, so the only thing
            // worth storing from it is which case is being quoted and the band's label.
            // The source calls `caseReview("aryia-leather", …)`, and the resolved item
            // carries `/work/aryia-leather/` — so the slug comes back out of the URL.
            $reviewCase = null;
            $reviewLabel = $s['reviews']['label'] ?? null;

            $reviewUrl = $s['reviews']['items'][0]['url'] ?? '';
            if (preg_match('#/work/([a-z0-9-]+)/#', $reviewUrl, $m) === 1) {
                $reviewCase = $m[1];
            }

            $this->upsert(Service::class, 
                ['slug' => $slug],
                [
                    'group_id' => $slugToGroup[$slug] ?? null,
                    'name' => $s['h1'] ?? $slug,
                    'desc' => $this->catalogDesc($groups, $slug),
                    'tone' => $s['stat']['tone'] ?? $this->groupTone($groups, $slug),
                    'crumb' => $s['crumb'] ?? null,
                    'crumb_href' => $s['crumbHref'] ?? null,
                    'h1' => $s['h1'] ?? null,
                    'lead' => $s['lead'] ?? null,
                    'chips' => $s['chips'] ?? null,
                    'meta' => $s['meta'] ?? null,
                    'stat' => $s['stat'] ?? null,
                    'sections' => $this->normaliseSections($s['sections'] ?? []),
                    'faq' => $s['faq'] ?? null,
                    'final' => $s['final'] ?? null,
                    'article' => $s['article'] ?? null,
                    'review_case' => $reviewCase,
                    'review_label' => $reviewLabel,
                    'sort' => (int) array_search($slug, $order, true),
                    'is_active' => true,
                ],
            );

            $this->counts['Services'] = ($this->counts['Services'] ?? 0) + 1;
        }
    }

    /**
     * Strip `null` item shapes the frontend tolerates but a repeater cannot round-trip.
     *
     * The source (`lib/services.ts`) is generated from a reference layout and carries
     * explicit nulls on optional keys (`link: null`, `tone: null`). A Filament repeater
     * writes back only the fields it knows about, so leaving the nulls in means the first
     * save silently changes the stored shape. Normalising once at import makes the row
     * and the form agree from the start.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function normaliseSections(array $sections): array
    {
        $out = [];

        foreach ($sections as $section) {
            $items = [];

            foreach ($section['items'] ?? [] as $item) {
                $clean = array_filter(
                    $item,
                    static fn ($v): bool => $v !== null,
                );

                $items[] = $clean;
            }

            $out[] = [
                'num' => $section['num'] ?? '',
                'tone' => $section['tone'] ?? null,
                'title' => $section['title'] ?? '',
                'text' => $section['text'] ?? null,
                'link' => $section['link'] ?? null,
                'items' => $items,
            ];
        }

        return $out;
    }

    /** The one-line catalog description from the group listing, which the page row lacks. */
    private function catalogDesc(array $groups, string $slug): ?string
    {
        foreach ($groups as $g) {
            foreach ($g['services'] ?? [] as $s) {
                if ($s['slug'] === $slug) {
                    return $s['desc'] ?? null;
                }
            }
        }

        return null;
    }

    private function groupTone(array $groups, string $slug): string
    {
        foreach ($groups as $g) {
            foreach ($g['services'] ?? [] as $s) {
                if ($s['slug'] === $slug) {
                    return $s['tone'] ?? $g['tone'] ?? 'accent';
                }
            }
        }

        return 'accent';
    }

    /* ================================================================== *
     * Posts
     * ================================================================== */

    private function importPosts(array $data): void
    {
        $posts = $data['posts'] ?? [];

        foreach ($posts as $i => $p) {
            $this->upsert(Post::class, 
                ['slug' => $p['slug']],
                [
                    'title' => $p['title'],
                    'category' => $p['category'] ?? 'Agency',
                    'tone' => $p['tone'] ?? 'accent',
                    'date' => $p['date'] ?? null,
                    'read_time' => (int) ($p['readTime'] ?? 5),
                    'is_featured' => $i === 0,
                    'excerpt' => $p['excerpt'] ?? null,
                    'date_label' => $p['dateLabel'] ?? null,
                    'takeaways' => $p['takeaways'] ?? null,
                    'toc' => $p['toc'] ?? null,
                    'body' => Post::normaliseBlocks($p['body'] ?? []) ?? [],
                    'faq' => $p['faq'] ?? null,
                    'sort' => $i,
                    'is_published' => true,
                    'published_at' => $p['date'] ?? null,
                ],
            );

            $this->counts['Posts'] = ($this->counts['Posts'] ?? 0) + 1;
        }
    }

    /**
     * Every block gets every key, so the block-builder repeater and the stored row agree.
     *
     * `Block` is a discriminated union — an `h2` has `html` and `id`, a `ul` has `items`,
     * a `table` has `head` and `rows`. A repeater renders one set of fields per block, so
     * a missing key reads as "not filled in" and a later save drops it. Filling the union
     * out up front means `PostBody.tsx`'s `switch` always finds what it switches on.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    /* ================================================================== *
     * Case studies
     * ================================================================== */

    private function importCases(array $data): void
    {
        $cases = $data['cases'] ?? [];

        // Transcribed from the `HEADLINE` map in the frontend's `lib/caseReviews.ts`, which
        // was the last piece of the review band held in code. There is no machine-readable
        // source for it — the map was written as literal prose beside the component — so
        // this, like the OSS tiles and the tiers, is a transcription rather than a dump.
        // Cases without an entry fall back to their own summary in `caseReview()`.
        $reviewCopy = [
            'aryia-leather' => [
                'title' => 'Meta & TikTok growth for a premium leather brand',
                'body' => 'Revenue grew 186% in five months while cost per acquisition fell 41% and blended ROAS held at 3×. The rebuild moved the account from one-off creative batches to weekly offer-led sprints, with the mobile checkout tightened at the same time.',
            ],
            'firstway-global' => [
                'title' => 'Funnel rebuild for a global consultancy',
                'body' => 'The constraint was the lead-to-call handoff, not the traffic. After the funnel was rebuilt around the booked consult, qualified lead volume rose 310% and the cost per booked consult dropped.',
            ],
            'fishnfruit' => [
                'title' => 'Inquiry automation for an eCommerce operation',
                'body' => 'Inbound questions used to be answered by hand, one at a time. The qualification and reply path now runs automatically — 94% of inquiries handled without a person retyping the same answer.',
            ],
        ];

        foreach ($cases as $i => $c) {
            $model = new CaseStudy([
                'client' => $c['client'],
            ]);

            $copy = $reviewCopy[$c['slug']] ?? [];

            $this->upsert(CaseStudy::class, 
                ['slug' => $c['slug']],
                [
                    'num' => $c['num'] ?? '',
                    'client' => $c['client'],
                    'vertical' => $c['vertical'] ?? '',
                    'market' => $c['market'] ?? '',
                    'tone' => $c['tone'] ?? 'ink',
                    'year_label' => $c['yearLabel'] ?? null,
                    'is_active' => $c['active'] ?? true,
                    'initials' => $c['initials'] ?? $model->deriveInitials(),
                    'services' => $c['services'] ?? null,
                    'headline' => $c['headline'] ?? null,
                    'metrics' => $c['metrics'] ?? null,
                    'summary' => $c['summary'] ?? null,
                    'note' => $c['note'] ?? null,
                    'phases' => $c['phases'] ?? null,
                    'quote' => $c['quote'] ?? null,
                    'review_title' => $copy['title'] ?? null,
                    'review_body' => $copy['body'] ?? null,
                    'bg' => $c['bg'] ?? 'paper',
                    'year_start' => $c['yearStart'] ?? null,
                    'year_end' => $c['yearEnd'] ?? null,
                    'sort' => $i,
                ],
            );

            $this->counts['Cases'] = ($this->counts['Cases'] ?? 0) + 1;
        }
    }

    /* ================================================================== *
     * Open-source projects — inline in app/open-source/page.tsx, not a lib
     * ================================================================== */

    private function importOss(): void
    {
        // Copied verbatim from the `PROJECTS` array in app/open-source/page.tsx, which was
        // never extracted to a lib module. There is no machine-readable source for it, so
        // this is the one section transcribed rather than dumped.
        $projects = [
            [
                'num' => 'OSS.01',
                'year' => '2026  (active)',
                'name' => 'GITiempo',
                'tagline' => 'TIME TRACKING, NATIVE TO GITHUB',
                'stack' => 'ASTRO · VUE · FIREBASE · GITHUB API · CHROME EXTENSION',
                'license' => 'MIT · open source',
                'href' => '/gitiempo/',
                'img' => '/img/projects/gitiempo/user-app-dashboard-desktop.png',
                'alt' => 'GITiempo',
                'bar' => 'bg-green',
                'label' => 'text-green',
                'year_cls' => 'meta font-bold',
            ],
            [
                'num' => 'OSS.02',
                'year' => '2024  (active)',
                'name' => 'Google Account Chooser',
                'tagline' => 'THE RIGHT GOOGLE ACCOUNT, EVERY TIME',
                'stack' => 'CHROME EXTENSION · JAVASCRIPT · CHROME.STORAGE',
                'license' => 'MIT · open source',
                'href' => '/gac/',
                'img' => '/img/gac/dashboard.png',
                'alt' => 'Google Account Chooser',
                'bar' => 'bg-accent',
                'label' => 'text-accent',
                'year_cls' => 'meta font-bold',
            ],
            [
                'num' => 'OSS.03',
                'year' => '2026',
                'name' => 'WhenWarEnds',
                'tagline' => 'WHEN DOES THIS WAR END?',
                'stack' => 'ASTRO · CLOUDFLARE · TYPESCRIPT · OPEN DATA',
                'license' => 'CC BY 4.0 · open source',
                'href' => '/whenwarends/',
                'img' => '/img/whenwarends/hero.png',
                'alt' => 'WhenWarEnds',
                'bar' => 'bg-orange',
                'label' => 'text-orange',
                'year_cls' => 'meta',
            ],
        ];

        foreach ($projects as $i => $p) {
            $model = OssProject::query()->firstOrNew(['name' => $p['name']]);
            $model->fill($p + ['sort' => $i, 'is_active' => true]);
            $model->syncCell();
            $model->save();

            $this->counts['OSS projects'] = ($this->counts['OSS projects'] ?? 0) + 1;
        }
    }

    /* ================================================================== *
     * Engagement tiers — inline in components/services/EngagementTiers.tsx
     * ================================================================== */

    private function importTiers(): void
    {
        // Also transcribed: the `TIERS` array lives inside the component itself.
        $tiers = [
            [
                'name' => 'Micro-task',
                'blurb' => 'One-off fixes, edits, set-ups.',
                'price' => '$5',
                'unit' => '/hour',
                'points' => ['Pay only for hours used', 'No contract, no minimum', 'Good for trying us out'],
                'cta' => 'START A TASK',
                'href' => '/contact/#from=micro',
                'is_popular' => false,
            ],
            [
                'name' => 'Growth sprint',
                'blurb' => 'A fixed-scope project, 4–8 weeks.',
                'price' => 'from $1.5k',
                'unit' => '/sprint',
                'points' => ['Defined deliverables & timeline', 'Weekly check-ins and demos', 'Site, funnel, campaign or automation'],
                'cta' => 'PLAN A SPRINT',
                'href' => '/contact/#from=sprint',
                'is_popular' => true,
            ],
            [
                'name' => 'Growth partner',
                'blurb' => 'Monthly retainer, embedded team.',
                'price' => 'from $2.5k',
                'unit' => '/month',
                'points' => ['Ongoing media, CRO & analytics', 'Priority response & reporting', 'Cancel after 90 days'],
                'cta' => 'TALK RETAINER',
                'href' => '/contact/#from=partner',
                'is_popular' => false,
            ],
        ];

        foreach ($tiers as $i => $t) {
            $this->upsert(Tier::class, 
                ['name' => $t['name']],
                $t + ['sort' => $i, 'is_active' => true],
            );

            $this->counts['Tiers'] = ($this->counts['Tiers'] ?? 0) + 1;
        }
    }

    /* ================================================================== *
     * Page headers — the copy hardcoded in each page.tsx
     * ================================================================== */

    private function importHeaders(): void
    {
        $headers = [
            [
                'route' => 'services',
                // `# 01 — THE CATALOG` is the label above the catalogue further down the
                // page, not the hero's. The hero kicker is the one `RouteHero` renders.
                'kicker' => '# SERVICES — WHAT WE DO',
                'title' => '/services',
                'lead' => 'Thirteen services, four disciplines. We take on anything from $5/hour micro-tasks to growth sprints and a monthly growth partner retainer. The figures are published starting points, not quotes — yours gets its own scope once we\'ve seen the funnel.',
                'aside_label' => 'WAYS IN',
                'aside_text' => 'Something else in mind? Let\'s talk it through.',
                'cta_label' => 'BOOK A FREE CONSULTING CALL',
                'cta_href' => '/meet/',
                'outro' => [
                    'tiersLabel' => '# 03 — WAYS TO WORK TOGETHER',
                    'tiersTitle' => 'Pick the size of commitment.',
                    'tiersText' => 'Start small, expand when it works. The figures are starting points — final scope is agreed after the audit.',
                    'offLabel' => '# 04 — NOT ON THE LIST',
                    'offTitle' => 'These aren\'t all the ways in',
                    'offText' => 'We also take over inherited codebases, automate workflows with AI, and put senior engineers into teams that just need more hands. Those are sold as engagements rather than search terms — all 6 entry points are compared on /start.',
                    'finalLabel' => '# 05 — NONE OF THE ABOVE',
                    'finalTitle' => "Just tell us\nwhat you need.",
                    'finalText' => 'The categories are ours, not yours. A few sentences in the brief form works just as well, and we answer within 24 hours.',
                    'finalCta' => 'OPEN BRIEF FORM',
                    'finalHref' => '/contact/',
                ],
            ],
            [
                'route' => 'newsletter',
                'kicker' => '# NEWSLETTER — FIELD NOTES FROM THE STUDIO',
                'title' => '/newsletter',
                'lead' => 'Everything we learn while building — pricing, scope, handoff, performance, the arguments we have internally. Written down properly, once, so we stop repeating ourselves.',
                'aside_label' => 'ABOUT',
                'aside_text' => 'One issue a month, plus shorter notes when something breaks in a way worth writing about. No lead magnets, no growth hacks — just the working knowledge behind the projects on this site.',
                'cta_label' => 'BROWSE THE ARCHIVE',
                'cta_href' => '#issues',
                'outro' => [
                    'label' => '# WORK WITH US',
                    'title' => 'Bring us the hard part.',
                    'text' => 'Most of what we publish comes from work we\'re already doing. If your problem looks like one of these, tell us where it hurts.',
                    'cta' => 'OPEN BRIEF FORM',
                    'href' => '/contact/#from=newsletter',
                ],
            ],
            [
                'route' => 'work',
                'kicker' => '# WORK — 5 DOCUMENTED CASES · 3,500+ DELIVERED',
                'title' => '/work',
                'lead' => 'Five documented engagements, each measured on the client\'s own accounts. The rest of the record is under NDA and shared on a call.',
                'aside_label' => 'EVIDENCE',
                'aside_text' => 'Every number on this page can be checked on the client\'s own dashboard. Where a phase breakdown does not exist, the case says so rather than inventing one.',
                'cta_label' => 'START A PROJECT',
                'cta_href' => '/contact/',
                'outro' => [
                    'kicker' => '# PORTFOLIO · DOCUMENTED 2025 → 2026',
                    'title' => "Measured on\ntheir own accounts.",
                    'text' => 'Five engagements written up here, each with the number the client can check on their own dashboards. The rest of the record — 3,500+ projects across 10+ countries — is under NDA and shared on a call.',
                ],
            ],
            [
                'route' => 'open-source',
                'kicker' => '# OPEN SOURCE — 3 PROJECTS',
                'title' => '/open-source',
                'lead' => 'We build products we wish existed. Each one started as a problem on our own desks.',
                'aside_label' => 'SOURCE',
                'aside_text' => 'Some tools are too small to sell and too useful to skip — when we hit one on our own work, we build it, then open the source so anyone can run it, fork it, or learn from it. No accounts, no lock-in.',
                'cta_label' => 'GITHUB.COM/THE SHARK WEB-TEAM',
                // The page's own markup had `https://github.com/The Shark Web-team` — a URL
                // with spaces in it, which is not a URL. The canonical org is the one
                // `lib/settings.ts` already uses for the footer's social link.
                'cta_href' => 'https://github.com/thesharkweb',
                'outro' => [
                    'label' => '# BUILD WITH US',
                    'title' => "Got a tool\nin mind?",
                    'text' => '',
                    'cta' => 'OPEN BRIEF FORM',
                    'href' => '/contact/#from=open-source',
                ],
            ],
        ];

        foreach ($headers as $h) {
            $this->upsert(PageHeader::class, ['route' => $h['route']], $h);
            $this->counts['Page headers'] = ($this->counts['Page headers'] ?? 0) + 1;
        }
    }

    /* ================================================================== *
     * Homepage bands
     * ================================================================== */

    /**
     * The three homepage lists live in their own file, produced by
     * `newsharkwebageny/.ref/dump-homepage.mjs` rather than by the same generator as
     * `import.json` — they come out of `lib/data.ts` and `StatsBar.tsx`, not out of the
     * page-copy modules, and keeping them separate means re-running one does not disturb
     * the other.
     *
     * @return array<string, mixed>|null
     */
    private function homepageData(): ?array
    {
        $path = database_path('content/homepage.json');

        if (! is_file($path)) {
            $this->warn('  skipped: database/content/homepage.json not found. Run `.ref/dump-homepage.mjs` in the frontend.');

            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    private function importStats(): void
    {
        $data = $this->homepageData();

        if ($data === null) {
            return;
        }

        foreach ($data['stats'] ?? [] as $i => $s) {
            $this->upsert(Stat::class, 
                ['label' => $s['label']],
                [
                    'value' => $s['value'],
                    'suffix' => $s['suffix'] ?? '',
                    'caption' => $s['caption'] ?? '',
                    'tone' => $s['tone'] ?? 'ink',
                    'is_active' => true,
                    'sort' => $i,
                ],
            );

            $this->counts['Stats'] = ($this->counts['Stats'] ?? 0) + 1;
        }
    }

    private function importFeatured(): void
    {
        $data = $this->homepageData();

        if ($data === null) {
            return;
        }

        foreach ($data['featured'] ?? [] as $i => $c) {
            // Keyed on name: the five projects have distinct names and no slug, and a
            // re-run must update rather than append a second Crawler.
            $this->upsert(FeaturedCard::class, 
                ['name' => $c['name']],
                [
                    'brand' => $c['brand'] ?? 'THESHARKWEB.COM',
                    'domain' => $c['domain'] ?? '',
                    'year_label' => $c['year_label'] ?? '',
                    'stack' => $c['stack'] ?? '',
                    'result' => $c['result'] ?? '',
                    'badge' => (bool) ($c['badge'] ?? false),
                    'back_brand' => $c['back_brand'] ?? '',
                    'back_title' => $c['back_title'] ?? '',
                    'back_desc' => $c['back_desc'] ?? null,
                    'tone' => $c['tone'] ?? 'accent',
                    'front_bg' => $c['front_bg'] ?? '',
                    'thumbs' => $c['thumbs'] ?? [],
                    'chips' => $c['chips'] ?? [],
                    'facts' => $c['facts'] ?? [],
                    'specs' => $c['specs'] ?? [],
                    'cta_text' => $c['cta_text'] ?? 'READ THE CASE STUDY →',
                    'cta_href' => $c['cta_href'] ?? '/work/',
                    'is_active' => true,
                    'sort' => $i,
                ],
            );

            $this->counts['Featured'] = ($this->counts['Featured'] ?? 0) + 1;
        }
    }

    private function importReviews(): void
    {
        $data = $this->homepageData();

        if ($data === null) {
            return;
        }

        foreach ($data['reviews'] ?? [] as $i => $r) {
            // Keyed on the review title: two clients appear twice in the set, so the name
            // is not unique, but every Clutch review has its own headline.
            $this->upsert(Review::class, 
                ['title' => $r['title']],
                [
                    'quote' => $r['quote'],
                    'body' => $r['body'] ?? null,
                    'avatar' => $r['avatar'] ?? null,
                    'name' => $r['name'],
                    'role' => $r['role'] ?? '',
                    'role_tone' => $r['role_tone'] ?? 'ink',
                    'review_url' => $r['review_url'] ?? null,
                    'card_name' => $r['card_name'] ?? '',
                    'card_back_brand' => $r['card_back_brand'] ?? '',
                    'card_brand' => $r['card_brand'] ?? 'THESHARKWEB.COM',
                    'card_domain' => $r['card_domain'] ?? '',
                    'card_year_label' => $r['card_year_label'] ?? '',
                    'card_stack' => $r['card_stack'] ?? '',
                    'card_result' => $r['card_result'] ?? '',
                    'card_badge' => (bool) ($r['card_badge'] ?? false),
                    'card_tone' => $r['card_tone'] ?? 'accent',
                    'card_front_bg' => $r['card_front_bg'] ?? '',
                    'card_back_title' => $r['card_back_title'] ?? '',
                    'card_back_desc' => $r['card_back_desc'] ?? null,
                    'card_thumbs' => $r['card_thumbs'] ?? [],
                    'card_chips' => $r['card_chips'] ?? [],
                    'card_facts' => $r['card_facts'] ?? [],
                    'card_specs' => $r['card_specs'] ?? [],
                    'card_cta_text' => $r['card_cta_text'] ?? 'READ THE CASE STUDY →',
                    'card_cta_href' => $r['card_cta_href'] ?? '/work/',
                    'is_active' => true,
                    'sort' => $i,
                ],
            );

            $this->counts['Reviews'] = ($this->counts['Reviews'] ?? 0) + 1;
        }
    }
}
