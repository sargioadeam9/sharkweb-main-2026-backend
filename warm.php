<?php

declare(strict_types=1);

/**
 * Pre-compile the framework and every route the frontend calls.
 *
 * WHY. `php -S` binds its port the instant it starts, so the first request that arrives
 * pays for parsing ~1500 PHP files — measured at 3.8s with a warm disk cache and 17s on a
 * cold one. The frontend gives up after 10 seconds and silently falls back to the bundled
 * `lib/*.ts` copy, which Next then caches for five minutes. So a slow first request does
 * not just look slow, it can pin the wrong content in place for a while.
 *
 * This runs BEFORE the server binds, so there is no window in which a request can arrive
 * cold. It dispatches real requests through the HTTP kernel rather than just booting, so
 * the route files, controllers, form requests, resources and models all get compiled too —
 * booting alone leaves most of them untouched.
 *
 * Run with `opcache.enable_cli=1` and the same `opcache.file_cache` directory as the
 * server, so the compiled opcodes are written where the server process will find them.
 */

$root = __DIR__;

require $root.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require $root.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Boot before reading config: `bootstrap/app.php` returns the container, but config is only
// loaded once the kernel boots, and `$app['config']` throws before that.
$kernel->bootstrap();

// The key the content routes require. Read from config AFTER boot rather than by parsing
// .env: the file contains `|` inside a comment, which makes `parse_ini_file` fail outright,
// and the config value is what `CheckApiKey` actually compares against anyway.
$key = (string) $app['config']->get('sharkweb.api_key', '');

$routes = [
    '/api/v1/health',
    '/api/v1/content/services',
    '/api/v1/content/newsletter',
    '/api/v1/content/work',
    '/api/v1/content/open-source',
    '/api/v1/content/tiers',
    '/api/v1/content/page-headers',
    // The panel's login page. Filament is the heaviest thing in this app and it is not on
    // any API path, so without this the first visit to /theshark-hq costs ~28s of class
    // compilation — long enough that an editor assumes the panel is broken. This page is
    // public, so it warms without a session; the authenticated screens behind it are then
    // fast because they share almost all of the same classes.
    '/theshark-hq/login',
];

$started = microtime(true);

foreach ($routes as $uri) {
    $request = Illuminate\Http\Request::create(
        $uri,
        'GET',
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$key,
        ],
    );

    try {
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            fwrite(STDERR, "[warm] {$uri} -> {$status}\n");
        }
    } catch (Throwable $e) {
        // A warm-up must never stop the server from starting.
        fwrite(STDERR, "[warm] {$uri} failed: {$e->getMessage()}\n");
    }
}

printf("[warm] %d routes in %.2fs\n", count($routes), microtime(true) - $started);
