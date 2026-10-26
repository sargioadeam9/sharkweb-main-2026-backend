<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the lead scheduled a discovery booking on Cal.com.
 *
 * Stamped by the Cal.com webhook receiver (`POST /api/v1/webhooks/calcom`) when an appointment
 * is booked. The daily `leads:booking-reminder` command checks this column and skips any lead
 * with a timestamp present so booked prospects are not badgered with reminder emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->timestamp('booked_at')->nullable()->after('booking_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('booked_at');
        });
    }
};