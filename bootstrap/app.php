<?php

use App\Http\Middleware\CheckApiKey;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Applied to every /api/v1 route. The visitor's browser never calls this API —
        // Next.js Server Actions proxy every request — so the whole surface is
        // authenticated, not only the write endpoints.
        $middleware->alias([
            'api.key' => CheckApiKey::class,
        ]);

        $middleware->api(prepend: [
            SecurityHeaders::class,
        ]);

        // Honour X-Forwarded-For only from machines the deployment explicitly trusts
        // (see the TRUSTED_PROXIES block in .env.example for the trade-offs). Empty —
        // the default — means the web server terminates client connections directly and
        // no header is trusted, which is the safe direction: an untrusted
        // X-Forwarded-For is a rate-limit bypass, not a convenience.
        //
        // This reads env() directly instead of config(): the middleware callback runs
        // BEFORE the config repository is registered, so config() here is a fatal
        // error. The env() call is cache-safe — bootstrap executes on every request
        // regardless of `config:cache`, so the value is always read fresh from the
        // environment. The deliberate exception to the "no env() outside config"
        // rule, because this is the one place config() cannot reach.
        $trusted = (string) env('TRUSTED_PROXIES', '');
        if ($trusted !== '') {
            $middleware->trustProxies(at: $trusted === '*'
                ? '*'
                : array_map('trim', explode(',', $trusted)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON rendering for anything under /api, so a 404 or 500 never returns an
        // HTML error page into the frontend's fetch() call.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
