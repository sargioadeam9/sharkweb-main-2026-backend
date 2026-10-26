<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two browser identifiers the Conversions API needs to match an event to a person.
 *
 * WHY THEY ARE STORED. `fbc` is built from the `fbclid` on the ad's landing URL and `fbp` is
 * the cookie the pixel sets. Without them a server-side event is matched on hashed email
 * alone, which on iOS and behind an ad blocker frequently is not enough for Meta to accept
 * the match at all. Keeping them on the row also makes "why did this lead not match?" an
 * answerable question after the fact instead of a guess.
 *
 * NULLABLE, AND ALWAYS WILL BE. A visitor who arrives from an organic search has no
 * `fbclid`; one who declines the pixel has no `fbp`. Their lead is still a lead.
 *
 * BOTH ARE OPAQUE STRINGS, not secrets: `fb.1.1700000000.IwAR123…`. 255 is the documented
 * shape of `fbc` with room to spare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('fbc', 255)->nullable()->after('referrer_url');
            $table->string('fbp', 255)->nullable()->after('fbc');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn(['fbc', 'fbp']);
        });
    }
};
