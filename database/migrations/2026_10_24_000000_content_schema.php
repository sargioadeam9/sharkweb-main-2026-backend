<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The editable content layer (2026-10-24).
 *
 * WHY THESE TABLES EXIST. The site's copy for /services, /newsletter, /work and
 * /open-source used to live in hand-written TypeScript (`lib/services.ts` and friends,
 * ~6,000 lines) which only a developer could change. These tables move that content
 * into the admin panel so the owner can add, edit, delete and upload without a deploy.
 *
 * THE TS MODULES ARE NOT DELETED. They stay in the frontend as the fallback that
 * `lib/content.ts` merges under the live API response, so a backend outage degrades to
 * "yesterday's copy" instead of a blank page. That is why every column here is nullable
 * or defaulted — a half-filled row must not be able to break a render.
 *
 * SHAPE: SCALARS AS COLUMNS, NESTED LISTS AS JSON.
 *
 * This is the central design decision, so it is worth stating plainly. Each table's
 * *identity* fields — slug, name, title, order, publish state — are real columns, because
 * those are what the Filament list screen sorts, searches and filters on. Everything
 * *inside* a record that is genuinely a list — a service's sections and their items, a
 * post's body blocks, a case's metrics and phases, a tier's bullet points — is a JSON
 * column driven by a repeater on the edit form.
 *
 * The alternative was a table per list (service_sections, service_items, post_blocks,
 * case_phases, …), which would have meant roughly fourteen tables for content that is
 * only ever read and written as one page at a time. That buys queryability nobody needs
 * and costs a form that cannot be saved in a single transaction.
 *
 * SQLite ONLY, like `baseline_schema`. Laravel's `json` maps to `text` here, which is
 * what the live database already uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ------------------------------------------------------------------ *
         * Service groups — the four disciplines on /services
         * ------------------------------------------------------------------ */

        Schema::create('content_service_groups', function (Blueprint $table): void {
            $table->id();

            // The catalog's own numbering ("01".."04"). Kept as a string because it is
            // display copy, not an ordering key — `sort` below is the ordering key.
            $table->string('num', 8)->default('');
            $table->string('key', 64)->unique();

            $table->string('name');
            $table->string('tone', 16)->default('accent');
            $table->text('blurb')->nullable();

            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });

        /* ------------------------------------------------------------------ *
         * Services — one row per service page (13 of them)
         * ------------------------------------------------------------------ */

        Schema::create('content_services', function (Blueprint $table): void {
            $table->id();

            $table->string('slug', 96)->unique();
            $table->foreignId('group_id')->nullable()
                ->constrained('content_service_groups')->nullOnDelete();

            // Grid columns: what the list screen shows and searches without opening a row.
            $table->string('name');
            $table->string('desc')->nullable();
            $table->string('tone', 16)->default('accent');

            // Header block.
            $table->string('crumb')->nullable();
            $table->string('crumb_href', 128)->nullable();
            $table->string('h1')->nullable();
            $table->text('lead')->nullable();
            $table->json('chips')->nullable();

            // Nested structure. Repeater-driven, see the class docblock.
            $table->json('meta')->nullable();      // { title, description }
            $table->json('stat')->nullable();      // label, tone, big, small, title, text, cta
            $table->json('sections')->nullable();  // [{ num, tone, title, text, link, items: [...] }]
            $table->json('faq')->nullable();       // { label, tone, title, items: [...], note }
            $table->json('final')->nullable();     // label, title, text, tone, cta, href, links
            $table->json('article')->nullable();   // optional promo block

            /*
             * The testimonial band. A case-study SLUG, not a resolved snapshot.
             *
             * `caseReview()` rebuilds the whole band — the deck card included — from the
             * case record, so storing the resolved payload here would freeze a copy of
             * that case into this row. Editing the case would then leave thirteen service
             * pages quoting the old numbers. Resolving at render keeps one source of truth.
             */
            $table->string('review_case', 96)->nullable();
            $table->string('review_label', 96)->nullable();

            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
            $table->index('group_id');
        });

        /* ------------------------------------------------------------------ *
         * Newsletter posts
         * ------------------------------------------------------------------ */

        Schema::create('content_posts', function (Blueprint $table): void {
            $table->id();

            $table->string('slug', 128)->unique();
            $table->string('title');

            // Grid columns.
            $table->string('category', 64)->default('Agency');
            $table->string('tone', 16)->default('accent');
            $table->date('date')->nullable();
            $table->unsignedSmallInteger('read_time')->default(5);
            $table->boolean('is_featured')->default(false);

            $table->text('excerpt')->nullable();
            $table->string('date_label', 64)->nullable();

            // Nested structure, all repeater-driven.
            $table->json('takeaways')->nullable();
            $table->json('toc')->nullable();
            $table->json('body')->nullable();      // the block builder
            $table->json('faq')->nullable();

            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_published', 'sort']);
            $table->index('published_at');
        });

        /* ------------------------------------------------------------------ *
         * Case studies
         * ------------------------------------------------------------------ */

        Schema::create('content_cases', function (Blueprint $table): void {
            $table->id();

            $table->string('slug', 96)->unique();
            $table->string('num', 8)->default('');
            $table->string('client');

            // Grid columns.
            $table->string('vertical', 96)->default('');
            $table->string('market', 96)->default('');
            $table->string('tone', 16)->default('ink');
            $table->string('year_label', 32)->nullable();
            $table->boolean('is_active')->default(true);

            $table->string('initials', 8)->nullable();
            $table->json('services')->nullable();
            $table->json('headline')->nullable();  // { value, label }
            $table->json('metrics')->nullable();
            $table->text('summary')->nullable();
            $table->text('note')->nullable();
            $table->json('phases')->nullable();
            $table->json('quote')->nullable();
            $table->string('bg', 24)->default('paper');
            $table->string('year_start', 8)->nullable();
            $table->string('year_end', 8)->nullable();

            $table->unsignedInteger('sort')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });

        /* ------------------------------------------------------------------ *
         * Open-source projects
         * ------------------------------------------------------------------ */

        Schema::create('content_oss_projects', function (Blueprint $table): void {
            $table->id();

            $table->string('num', 16)->default('');
            $table->string('name');
            $table->string('href', 255)->default('');

            // Grid columns.
            $table->string('year', 32)->default('');
            $table->string('label', 16)->default('accent');
            $table->boolean('is_active')->default(true);

            $table->string('tagline')->nullable();
            $table->string('stack')->nullable();
            $table->string('license')->nullable();
            $table->string('alt')->nullable();

            // `img` is kept as a plain path so the bundled fallback files keep working;
            // `media` is what an upload writes to. The frontend prefers media.
            $table->string('img', 255)->nullable();
            $table->string('bar', 24)->default('bg-accent');
            $table->string('cell', 255)->nullable();
            $table->string('year_cls', 64)->nullable();

            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });

        /* ------------------------------------------------------------------ *
         * Engagement tiers — "Pick the size of commitment"
         * ------------------------------------------------------------------ */

        Schema::create('content_tiers', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('price', 64)->default('');
            $table->string('unit', 32)->default('');
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);

            $table->string('blurb')->nullable();
            $table->json('points')->nullable();
            $table->string('cta', 64)->nullable();
            $table->string('href', 255)->nullable();

            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });

        /* ------------------------------------------------------------------ *
         * Page headers — the copy above the content on each route
         * ------------------------------------------------------------------ *
         *
         * The route key ("services", "newsletter", "work", "open-source") is the
         * lookup, so exactly one row per page.
         */
        Schema::create('content_page_headers', function (Blueprint $table): void {
            $table->id();

            $table->string('route', 64)->unique();

            // Grid columns.
            $table->string('kicker')->nullable();
            $table->string('title')->nullable();

            // The hero: a lead paragraph plus a sidebar card.
            $table->text('lead')->nullable();
            $table->string('aside_label', 64)->nullable();
            $table->text('aside_text')->nullable();

            // An optional header CTA — used by /open-source's GitHub button.
            $table->string('cta_label', 64)->nullable();
            $table->string('cta_href', 255)->nullable();

            // The closing band: "Got a tool in mind?", "Just tell us what you need."
            $table->json('outro')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_page_headers');
        Schema::dropIfExists('content_tiers');
        Schema::dropIfExists('content_oss_projects');
        Schema::dropIfExists('content_cases');
        Schema::dropIfExists('content_posts');
        Schema::dropIfExists('content_services');
        Schema::dropIfExists('content_service_groups');
    }
};
