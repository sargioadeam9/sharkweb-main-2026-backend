#!/usr/bin/env bash
#
# Local API server.
#
# WHY THIS EXISTS RATHER THAN `php artisan serve`.
#
#   1. `artisan serve` does not bind on this machine. It starts, prints nothing, and
#      nothing ever listens on the port. Reproduced twice; do not reach for it.
#
#   2. Without opcache, every request re-parses ~1500 PHP files: a measured 1.9-2.2s each,
#      for the life of the server. `php -S` is one long-lived process, so opcache actually
#      persists here and the same request drops to ~0.10-0.14s. That is not a nicety: the
#      frontend's `backendGet()` gives up after 10 seconds, and a slow backend makes it
#      silently fall back to the bundled `lib/*.ts` content — which Next then caches for
#      five minutes. A slow API here looks exactly like "the panel isn't working".
#
#   3. `php -S` binds its port immediately, so the first request that arrives pays to
#      compile everything (3.8s warm-disk, 17s cold-disk). `warm.php` runs BEFORE the bind
#      and dispatches every route the frontend calls, so no request can arrive cold.
#
# Usage:
#   ./serve.sh            # 127.0.0.1:8000
#   PORT=8002 ./serve.sh  # another port
#
# `opcache.validate_timestamps=1` with `revalidate_freq=0` means an edited PHP file is
# picked up on the very next request. Nothing needs restarting after a code change.

set -euo pipefail

HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8000}"

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PUBLIC="$ROOT/public"
ROUTER="$ROOT/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"

if [ ! -f "$ROUTER" ]; then
    echo "Laravel's dev-server router is missing: $ROUTER" >&2
    echo "Run composer install first." >&2
    exit 1
fi

# Compiled opcodes are cached to disk as well as memory, so a fresh process does not have to
# re-parse everything after a restart. This is what turns a 17s first request into 4s.
CACHE_DIR="$ROOT/storage/framework/opcache"
mkdir -p "$CACHE_DIR"

COMMON_FLAGS=(
    -d zend_extension=php_opcache.dll
    -d opcache.enable=1
    -d opcache.memory_consumption=192
    -d opcache.interned_strings_buffer=16
    -d opcache.max_accelerated_files=20000
    -d opcache.validate_timestamps=1
    -d opcache.revalidate_freq=0
    -d opcache.file_cache="$CACHE_DIR"
)

# Warm before binding the port. Never fatal: if it fails the server still starts, just cold.
php -d opcache.enable_cli=1 "${COMMON_FLAGS[@]}" "$ROOT/warm.php" || true

cd "$PUBLIC"
exec php "${COMMON_FLAGS[@]}" -S "$HOST:$PORT" "$ROUTER"
