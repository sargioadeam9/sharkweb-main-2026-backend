<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (§7.2)
|--------------------------------------------------------------------------
|
| The browser never calls this API directly — Next.js Server Actions proxy every
| request — so CORS is a defence-in-depth layer rather than the primary control.
| It is still configured tightly, because "the browser never calls it" is an
| architectural intention, not a guarantee.
|
| `supports_credentials` is FALSE on purpose. Authentication is a Bearer token in
| an Authorization header, not a cookie, so credentialed CORS is unnecessary —
| and disabling it means the origin list never has to be combined with a
| wildcard, which is where most CORS misconfigurations come from.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://thesharkweb.com,https://www.thesharkweb.com'))
)));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => $origins,

    // Vercel preview deployments get a fresh subdomain per commit. Matching the pattern
    // keeps preview builds testable without hand-adding every generated host.
    'allowed_origins_patterns' => [
        '#^https://thesharkweb[a-z0-9-]*\.vercel\.app$#',
    ],

    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Api-Key', 'X-Requested-With'],

    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After'],

    'max_age' => 86400,

    'supports_credentials' => false,

];
