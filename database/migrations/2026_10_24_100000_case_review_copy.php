<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The testimonial band's long copy, moved from code into the database.
 *
 * WHY. `lib/caseReviews.ts` held a `HEADLINE` map: three entries of `{ title, body }`
 * keyed by case slug, written as literal prose next to the component that renders them.
 * That was the last piece of the review band an owner could not change — the label, the
 * quote and the selected case were all editable, but the paragraph under the quote was
 * not.
 *
 * NULLABLE ON PURPOSE. A case with no review copy falls back to the case's own summary in
 * `caseReview()`, so a newly added case is not blocked on somebody writing two paragraphs.
 * Blank and absent mean the same thing here, which is why the frontend tests for a
 * non-empty string rather than for the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_cases', function (Blueprint $table): void {
            $table->string('review_title', 200)->nullable()->after('quote');
            $table->text('review_body')->nullable()->after('review_title');
        });
    }

    public function down(): void
    {
        Schema::table('content_cases', function (Blueprint $table): void {
            $table->dropColumn(['review_title', 'review_body']);
        });
    }
};
