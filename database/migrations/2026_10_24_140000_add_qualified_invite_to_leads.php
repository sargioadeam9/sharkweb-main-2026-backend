<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the booking invitation was sent to a lead.
 *
 * WHY A COLUMN AND NOT JUST THE STATUS. "Qualified" says what the owner thinks of the lead;
 * this says whether we have already emailed them about it. Those are two different facts, and
 * the button needs the second one: without a stamp, every click on "Approve & invite" sends
 * another email, and a double-click sends two.
 *
 * Null means "never invited". The row action reads it to decide between "Approve & invite"
 * and "Resend invite".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->timestamp('qualified_invite_sent_at')->nullable()->after('internal_notes');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('qualified_invite_sent_at');
        });
    }
};
