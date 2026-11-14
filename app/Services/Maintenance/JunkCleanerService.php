<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

/**
 * Finds and removes the files shared hosting accumulates until the disk quota dies (§Maintenance).
 *
 * THE WHITELIST IS THE SAFETY MODEL. Nothing here walks the whole storage tree and
 * deletes "junk"; every removable category is an explicit, enumerated path pattern
 * that is safe BY CONSTRUCTION. Anything not on the list — media, uploads, the
 * database file, .env, the backup archives themselves — is invisible to this class
 * and can never be deleted by it, whatever a future edit does to the rest of the code.
 *
 * The scan → preview → confirm flow lives in the Filament page; this service only
 * answers two questions: what is there (scan) and remove this category (clean).
 */
class JunkCleanerService
{
    /**
     * Paths that must never appear in any category, now or after any future edit.
     * Prefixes are relative to base_path(); '.env' also matches .env.backup style files.
     */
    public const NEVER_TOUCH = [
        '.env',
        'database/',
        'storage/app/public',
        'storage/app/private',
        'public/uploads',
    ];

    /**
     * True when a path touches something on the never-touch list. Called on every
     * candidate at scan time (so protected files never reach the UI) and again inside
     * clean() (so a future refactor cannot bypass the list).
     */
    public static function isProtected(string $path): bool
    {
        /* Normalize BOTH sides — base_path() carries backslashes on Windows. */
        $root = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalized = str_replace('\\', '/', $path);

        $relative = $normalized;

        if (str_starts_with($normalized, $root)) {
            $relative = substr($normalized, strlen($root));
        }

        foreach (self::NEVER_TOUCH as $guard) {
            $guard = str_replace('\\', '/', $guard);

            if (str_starts_with($relative, rtrim($guard, '/').'/') || $relative === $guard) {
                return true;
            }

            if ($guard === '.env' && str_starts_with(basename($relative), '.env')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every removable category with its current contents. Only non-empty categories
     * are returned. Each key maps to one cleanup concern. `keep` behaviour inside a
     * category (today's log, the current session) is applied by the item collectors.
     *
     * @return array<int, array{key: string, label: string, description: string, warning: string|null, items: array<int, array{path: string, size: int}>, size: int}>
     */
    public function scan(): array
    {
        $categories = [];

        /* Rotated application logs — everything except what is being written to now. */
        $items = $this->logFiles();
        $categories[] = $this->category(
            'rotated_logs',
            'Rotated application logs',
            'Old laravel-YYYY-MM-DD.log files. The current day\'s log always stays.',
            null,
            $items,
        );

        /* Compiled Blade views — regenerated on the next request, always. */
        $categories[] = $this->category(
            'compiled_views',
            'Compiled view cache',
            'storage/framework/views — Blade\'s compiled templates. Regenerated automatically; clearing is how you fix a stale view.',
            null,
            $this->directoryFiles(storage_path('framework/views')),
        );

        /* Application cache — settings cache, route/config arrays. Rebuilt on demand. */
        $categories[] = $this->category(
            'app_cache',
            'Application cache',
            'storage/framework/cache — cached settings, config and data arrays. Rebuilt on the next request; the site never misses a beat.',
            null,
            $this->directoryFiles(storage_path('framework/cache/data')),
        );

        /* Sessions — destructive in a way worth naming: it signs everyone out. */
        $categories[] = $this->category(
            'sessions',
            'Sessions',
            'storage/framework/sessions — every active login. Cleaning this signs every operator out of the panel.',
            'You (and anyone else) will be logged out of the admin panel.',
            $this->sessionFiles(),
        );

        /* Leftover extracted restore dumps — the restore engine deletes its own when it
           finishes, but a failed or abandoned run leaves one behind. */
        $categories[] = $this->category(
            'backup_temp',
            'Extracted restore dumps',
            'storage/app/backup-temp — temporary SQL files unpacked for a restore. Normally empty; a leftover means an old run died mid-way.',
            null,
            $this->directoryFiles(storage_path('app/backup-temp')),
        );

        return array_values(array_filter(
            $categories,
            fn (array $c) => $c['size'] > 0 || $c['items'] !== []
        ));
    }

    /**
     * Delete one category's files. Returns bytes freed.
     *
     * Every path is re-validated twice: against the never-touch list (defence in depth)
     * and against the whitelist via categoryItems() — a path is only removed if it
     * matched an enumerated category on a fresh scan, so nothing can be smuggled in
     * by editing the caller.
     */
    public function clean(string $key): int
    {
        $freed = 0;

        foreach ($this->categoryItems($key) as $item) {
            if (self::isProtected($item['path'])) {
                continue;
            }

            if (is_file($item['path']) && @unlink($item['path'])) {
                $freed += $item['size'];
            }
        }

        return $freed;
    }

    /** Only this service's own categories are ever cleanable. */
    private function categoryItems(string $key): array
    {
        foreach ($this->scan() as $category) {
            if ($category['key'] === $key) {
                return $category['items'];
            }
        }

        return [];
    }

    /**
     * Rotated logs: any laravel-*.log that is not today's, plus the legacy single-file
     * laravel.log that predates LOG_CHANNEL=daily (it reached 93MB before the fix).
     */
    private function logFiles(): array
    {
        $items = [];
        $today = 'laravel-'.now()->format('Y-m-d').'.log';

        foreach ((array) glob(storage_path('logs/laravel-*.log')) as $file) {
            $file = (string) $file;
            $name = basename($file);

            if ($name !== $today && ! self::isProtected($file)) {
                $items[] = ['path' => $file, 'size' => (int) filesize($file)];
            }
        }

        $legacy = storage_path('logs/laravel.log');

        if (is_file($legacy) && ! self::isProtected($legacy)) {
            $items[] = ['path' => $legacy, 'size' => (int) filesize($legacy)];
        }

        return $items;
    }

    /**
     * All files in one flat directory. Sub-directories are ignored on purpose — the
     * categories are about files this app created and recognises.
     */
    private function directoryFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $items = [];

        foreach ((array) glob($dir.'/*') as $path) {
            $path = (string) $path;

            if (is_file($path) && ! self::isProtected($path)) {
                $items[] = ['path' => $path, 'size' => (int) filesize($path)];
            }
        }

        return $items;
    }

    /** Session files, excluding this request's own session — the operator stays logged in. */
    private function sessionFiles(): array
    {
        $items = [];
        $current = session()->getId();

        foreach ($this->directoryFiles(storage_path('framework/sessions')) as $item) {
            if (basename($item['path']) !== $current) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<int, array{path: string, size: int}>  $items
     * @return array{key: string, label: string, description: string, warning: string|null, items: array<int, array{path: string, size: int}>, size: int}
     */
    private function category(string $key, string $label, string $description, ?string $warning, array $items): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'warning' => $warning,
            'items' => $items,
            'size' => (int) array_sum(array_column($items, 'size')),
        ];
    }
}
