<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server authentication (§7.1).
 *
 * The visitor's browser never calls this API. Every request arrives from a Next.js Server
 * Action carrying `Authorization: Bearer {LARAVEL_API_KEY}`. This middleware is the only
 * thing standing between the public internet and the lead database, so it fails closed.
 *
 * Two decisions worth stating:
 *
 *  1. **An unconfigured key returns 503, not "allow".** The tempting shortcut is to skip
 *     the check when `LARAVEL_API_KEY` is empty so local development "just works". That
 *     turns a forgotten environment variable on the production server into a completely
 *     open API. A 503 is loud and safe; a silent bypass is neither.
 *
 *  2. **Comparison is timing-safe** (`hash_equals`), so the key cannot be recovered
 *     byte-by-byte from response timings.
 */
class CheckApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('sharkweb.api_key');

        if ($configured === '') {
            return response()->json([
                'error' => 'server_misconfigured',
                'message' => 'API authentication is not configured on this server.',
            ], 503);
        }

        $provided = $this->extractToken($request);

        if ($provided === null || ! hash_equals($configured, $provided)) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'A valid API key is required.',
            ], 401);
        }

        return $next($request);
    }

    /**
     * Accept the standard `Authorization: Bearer` header, or `X-Api-Key` as a fallback
     * for tooling that cannot easily set an Authorization header.
     */
    private function extractToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            return $bearer;
        }

        $header = $request->header('X-Api-Key');

        return is_string($header) && $header !== '' ? $header : null;
    }
}
