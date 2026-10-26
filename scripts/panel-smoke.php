<?php

use App\Models\Content\CaseStudy;
use App\Models\Content\Post;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/*
 * Live panel smoke test — replays authenticated requests through the HTTP kernel and
 * reports the status, size and key markers for each page.
 *
 * Run:  php artisan tinker scripts/panel-smoke.php
 *
 * This exists because the test suite runs in CLI with no time limit, so it cannot catch a
 * page that exceeds the dev server's 30s execution limit. Replaying the real request through
 * the kernel does. See .workbuddy-ai/memory/INVARIANTS.md §5 for the 30s trap.
 */

$user = User::first();

if (! $user) {
    echo "No user found — create one with: php artisan make:filament-user\n";

    return;
}

Auth::login($user);

$kernel = app(Kernel::class);

$post = Post::first();
$case = CaseStudy::first();

/*
 * The content module is the reason this script exists in its current shape: the service
 * form is the largest in the panel (nested repeaters, 19 items across 3 sections) and the
 * newsletter block builder holds up to 200 rows. A full-page render of either is exactly
 * the kind of page that can cross a host's execution limit without the CLI test noticing.
 *
 * The edit targets are built from real records so the forms hydrate their actual stored
 * JSON — an empty form would render fine and prove nothing.
 */
$targets = [
    'dashboard' => ['/theshark-hq', []],
    'leads index' => ['/theshark-hq/leads', []],
    'users' => ['/theshark-hq/users', [
        'Users' => 'page heading',
    ]],
    'page copy' => ['/theshark-hq/page-headers', [
        'Services — /services' => 'route label',
    ]],
    'disciplines' => ['/theshark-hq/service-groups', [
        'Disciplines' => 'page heading',
    ]],
    'services index' => ['/theshark-hq/services', []],
    'service edit' => ['/theshark-hq/services/'.(optional(App\Models\Content\Service::first())->id).'/edit', [
        // "&" is entity-encoded in the rendered tab strip, so match the encoded form.
        'Copy &amp; SEO' => 'first tab',
        'Sections' => 'body tab',
    ]],
    'tiers' => ['/theshark-hq/tiers', [
        'Commitment tiers' => 'page heading',
    ]],
    'newsletter index' => ['/theshark-hq/posts', []],
    'newsletter edit' => [$post ? '/theshark-hq/posts/'.$post->id.'/edit' : '/theshark-hq/posts', [
        'Article body' => 'block builder tab',
    ]],
    'case studies' => ['/theshark-hq/case-studies', [
        'Case studies' => 'page heading',
    ]],
    'case edit' => [$case ? '/theshark-hq/case-studies/'.$case->id.'/edit' : '/theshark-hq/case-studies', [
        'The numbers' => 'metrics tab',
        'Phases' => 'narrative tab',
    ]],
    'open source' => ['/theshark-hq/oss-projects', [
        'Open source' => 'page heading',
    ]],
    'newsletter subscribers' => ['/theshark-hq/newsletter-subscribers', []],
    'site settings' => ['/theshark-hq/manage-settings', []],
    'backups' => ['/theshark-hq/manage-maintenance', [
        'Backups' => 'page heading',
        'Run backup now' => 'action button',
    ]],
    'cleaner' => ['/theshark-hq/manage-cleaner', [
        'Disk usage' => 'usage panel',
        'Never touched by the cleaner' => 'safety rail',
        'public/uploads' => 'never-touch entry',
        'Database pruning' => 'prune panel',
    ]],
];

$failures = 0;

foreach ($targets as $label => $spec) {
    [$path, $markers] = $spec;

    $request = Request::create($path, 'GET');
    $request->setLaravelSession(app('session.store'));

    $started = microtime(true);
    $response = $kernel->handle($request);
    $elapsed = round((microtime(true) - $started) * 1000);

    $body = $response->getContent();
    $status = $response->getStatusCode();

    if ($status !== 200) {
        $failures++;
    }

    printf("%-15s %s  %7d bytes  %6d ms\n", $label, $status, strlen($body), $elapsed);

    // A 500 renders a full error page (APP_DEBUG=true). Surface the exception itself rather
    // than leaving a wall of HTML to read.
    if ($status >= 500) {
        if (preg_match('/<title>(.*?)<\/title>/s', $body, $m)) {
            printf("                  EXCEPTION: %s\n", trim(html_entity_decode(strip_tags($m[1]))));
        }
        if (preg_match('/(Uncaught|Exception|Error)[^<]{0,300}/', strip_tags($body), $m)) {
            printf("                  %s\n", trim(preg_replace('/\s+/', ' ', $m[0])));
        }
        if (preg_match_all('/#\d+\s+([^\n<]{0,160})/', strip_tags($body), $frames)) {
            foreach (array_slice($frames[1], 0, 4) as $frame) {
                printf("                  frame: %s\n", trim($frame));
            }
        }
    }

    foreach ($markers as $needle => $description) {
        $found = stripos($body, $needle) !== false;
        if (! $found) {
            $failures++;
        }
        printf("                  %-28s %s\n", $description, $found ? 'OK' : 'MISSING');
    }
}

echo "\n".($failures === 0 ? 'All panel pages rendered.' : $failures.' problem(s) found.')."\n";
