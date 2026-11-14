<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three homepage bands that were still hardcoded in TypeScript.
 *
 * WHY ONE MIGRATION. They are the same change made three times: a list the owner could
 * not reach, lifted out of a `.ts` file into a table. Splitting them across three files
 * would only make the deploy order harder to reason about, and none of the three can be
 * half-applied — the frontend falls back to its bundled copy per band, so a missing table
 * degrades one section rather than the page.
 *
 * WHAT IS DELIBERATELY NOT A COLUMN. The deck cards carry `tilt`, `shift` and `z`, and the
 * reviews carry a whole nested card. Those are geometry and layout: they are derived from
 * a record's position in the list (see the models' `toFrontendArray()`). Exposing them
 * would turn the panel into a design tool and let an editor flatten the stack by typing
 * "0deg" into the wrong box.
 *
 * `content_featured_cards` IS ITS OWN TABLE, NOT A FLAG ON `content_cases`. The five
 * projects featured on the homepage are not the five case studies in `/work` — they are a
 * different set of client projects with no published case yet. A `featured` flag would
 * have had nothing to point at.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ------------------------------------------------------------------ *
         * Stats bar — the five figures under the hero
         * ------------------------------------------------------------------ */
        Schema::create('content_stats', function (Blueprint $table): void {
            $table->id();

            $table->string('label', 40);
            $table->string('value', 24);
            $table->string('suffix', 8)->default('');
            $table->string('caption', 80)->default('');

            // accent | green | orange | magenta | ink
            $table->string('tone', 16)->default('ink');

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });

        /* ------------------------------------------------------------------ *
         * Featured projects — the "# 02" deck on the homepage
         * ------------------------------------------------------------------ */
        Schema::create('content_featured_cards', function (Blueprint $table): void {
            $table->id();

            // Front face
            $table->string('name', 80);
            $table->string('brand', 80)->default('THESHARKWEB.COM');
            $table->string('domain', 60)->default('');
            $table->string('year_label', 40)->default('');
            $table->string('stack', 120)->default('');
            $table->string('result', 160)->default('');
            $table->boolean('badge')->default(false);

            // Back face
            $table->string('back_brand', 24)->default('');
            $table->string('back_title', 160)->default('');
            $table->text('back_desc')->nullable();

            // One tone drives brand, domain, back and CTA together, which is how the
            // five shipped cards were built. `front_bg` stays separate because the
            // existing set uses a yellow card for an ink-toned record.
            $table->string('tone', 16)->default('accent');
            $table->string('front_bg', 24)->default('blue-soft');

            // Nested lists, matching the other content tables' JSON-column convention.
            // thumbs: [{src} | {nda:true} | {text, metric:true}]
            $table->json('thumbs')->nullable();
            // chips: ["PYTHON", "NEST"] — the small tag row on the back
            $table->json('chips')->nullable();
            // facts: [{figure, tone, label, sub}]
            $table->json('facts')->nullable();
            // specs: [["domain", "Recruitment"], ["client", "under NDA"]]
            $table->json('specs')->nullable();

            $table->string('cta_text', 64)->default('READ THE CASE STUDY →');
            $table->string('cta_href', 255)->default('/work/');

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });

        /* ------------------------------------------------------------------ *
         * Client reviews — the "# 03" band, eighteen records
         * ------------------------------------------------------------------ */
        Schema::create('content_reviews', function (Blueprint $table): void {
            $table->id();

            $table->string('title', 200);
            $table->text('quote');
            $table->text('body')->nullable();

            $table->string('avatar', 255)->nullable();
            $table->string('name', 120);
            $table->string('role', 120)->default('');
            $table->string('role_tone', 16)->default('ink');

            $table->string('review_url', 255)->nullable();

            // The deck card shown beside each quote. Same fields as a featured card's
            // front face, minus the ones the review supplies itself (quote, avatar).
            //
            // `card_name` is the PROJECT on the card, not the reviewer — "Retail Network",
            // "StudyBooster", "SV Geo". In the shipped set it never matches `name`.
            $table->string('card_name', 80)->default('');
            // The number printed on the card's back. Per-review in the shipped set
            // (# 003, # 010, # 022 …) and not derivable from position.
            $table->string('card_back_brand', 24)->default('');
            $table->string('card_brand', 80)->default('THESHARKWEB.COM');
            $table->string('card_domain', 60)->default('');
            $table->string('card_year_label', 40)->default('');
            $table->string('card_stack', 120)->default('');
            $table->string('card_result', 160)->default('');
            $table->boolean('card_badge')->default(false);
            $table->string('card_tone', 16)->default('accent');
            $table->string('card_front_bg', 24)->default('blue-soft');
            $table->string('card_back_title', 160)->default('');
            $table->text('card_back_desc')->nullable();
            $table->json('card_thumbs')->nullable();
            $table->json('card_chips')->nullable();
            $table->json('card_facts')->nullable();
            $table->json('card_specs')->nullable();
            $table->string('card_cta_text', 64)->default('READ THE CASE STUDY →');
            $table->string('card_cta_href', 255)->default('/work/');

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reviews');
        Schema::dropIfExists('content_featured_cards');
        Schema::dropIfExists('content_stats');
    }
};
