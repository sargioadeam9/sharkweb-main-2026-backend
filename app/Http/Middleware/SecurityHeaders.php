<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for API responses (§7.3).
 *
 * The frontend sets its own CSP/HSTS for the HTML it serves; these cover the JSON API,
 * which is a different origin (`api.thesharkweb.com`).
 *
 * `X-Frame-Options: DENY` and `nosniff` are the meaningful ones here — they stop the API
 * being framed or having its JSON reinterpreted as script by an old browser.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // API responses are per-request and must never be stored by an intermediary.
        //
        // This OVERRIDES rather than defers. Laravel sets `Cache-Control: no-cache, private`
        // on JSON responses by default, and `no-cache` still permits a cache to store the
        // body and reuse it after revalidation — which is wrong for an authenticated
        // endpoint returning lead data. `no-store` forbids storage outright.
        // The frontend's ISR layer is the cache; this is the origin.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
