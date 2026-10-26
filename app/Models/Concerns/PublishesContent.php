<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared publication behaviour for Post and Portfolio.
 *
 * Both tables carry `status` + `published_at` with a `published` case value, so the
 * visibility rules live in one place rather than being duplicated per model.
 * `Service` is deliberately excluded — it uses `is_active` and has no scheduling.
 */
trait PublishesContent
{
    /**
     * Rows the public API is allowed to return.
     *
     * A row is live when its status is `published` AND its `published_at` is either
     * unset (publish immediately) or in the past. The second condition is what makes
     * scheduled publishing work: the Filament form can set a future `published_at`
     * with status `published` and the row simply stays invisible until then.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where(function (Builder $q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /** Newest first, with a stable tiebreak so pagination never repeats a row. */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    /** Instance-level equivalent of `scopeLive`. */
    public function isLive(): bool
    {
        if (($this->status?->value ?? $this->status) !== 'published') {
            return false;
        }

        return $this->published_at === null || $this->published_at->isPast();
    }

    /**
     * Paths affected by a given slug.
     *
     * Slug-aware on purpose: when an editor renames an article the observer must purge
     * the OLD path as well, otherwise the stale page stays cached and keeps serving.
     * Passing the slug in (rather than reading `$this->slug`) is what makes that possible.
     *
     * @return list<string>
     */
    abstract public function publicPathsForSlug(?string $slug): array;

    /** Paths affected by this record in its current state. */
    public function publicPaths(): array
    {
        return $this->publicPathsForSlug($this->slug);
    }

    /**
     * The single canonical URL for this record, used for IndexNow submission.
     * Returns null when the record has no standalone public page.
     */
    abstract public function canonicalPath(): ?string;

    /** Absolute canonical URL, or null when there is no public page. */
    public function canonicalUrl(): ?string
    {
        $path = $this->canonicalPath();

        if ($path === null) {
            return null;
        }

        return rtrim((string) config('sharkweb.frontend_url'), '/').'/'.ltrim($path, '/');
    }
}
