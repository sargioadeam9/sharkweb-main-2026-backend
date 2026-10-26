<?php

/*
 * Render the transactional emails to standalone HTML files for review.
 *
 * WHY: the emails are the only customer-facing output this backend produces, and nobody
 * should have to read a Blade template or parse a MIME dump from laravel.log to check what
 * a lead actually receives. This writes them out as browsable HTML.
 *
 * Run:  php artisan tinker scripts/mail-previews.php
 *
 * Output: storage/app/mail-previews/*.html  (gitignored)
 *
 * Locally MAIL_MAILER=log, so nothing is sent — the same templates render identically
 * whichever transport is used.
 */

use App\Mail\LeadAutoResponder;
use App\Mail\NewLeadAlert;
use App\Mail\WeeklyDigest;
use App\Models\Lead;
use App\Services\PerformanceDigestService;

$outDir = storage_path('app/mail-previews');

if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$lead = Lead::query()->orderByDesc('id')->first();

if (! $lead) {
    echo "No lead found — submit one first (POST /api/v1/leads).\n";

    return;
}

$previews = [
    'lead-auto-responder' => new LeadAutoResponder($lead),
    'new-lead-alert' => new NewLeadAlert($lead),
    'weekly-digest' => new WeeklyDigest(app(PerformanceDigestService::class)->metrics()),
];

echo "Lead #{$lead->id} — {$lead->displayName()} <{$lead->email}> (".($lead->lead_score?->value ?? 'unscored').")\n\n";

foreach ($previews as $name => $mailable) {
    $path = $outDir.'/'.$name.'.html';

    file_put_contents($path, $mailable->render());

    printf("%-22s %7d bytes  %s\n", $name, filesize($path), $path);
}

echo "\nOpen them in a browser. Note AGENCY_DECK_URL is unset, so the deck section is absent\n";
echo "from the auto-responder — that is the designed behaviour, not a missing block.\n";
