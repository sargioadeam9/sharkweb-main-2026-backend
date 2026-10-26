<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the one booking reminder went out.
 *
 * THE INVITATION IS SENT BY HAND, THE REMINDER IS NOT. So the reminder needs its own stamp:
 * a daily command has to know which leads it has already nudged, or it emails the same person
 * every morning until they book.
 *
 * Null means "never reminded". It is only written after a successful send, exactly like
 * `qualified_invite_sent_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->timestamp('booking_reminder_sent_at')->nullable()->after('qualified_invite_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('booking_reminder_sent_at');
        });
    }
};
