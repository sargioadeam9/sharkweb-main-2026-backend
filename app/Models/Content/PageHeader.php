<?php

declare(strict_types=1);

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Model;

/**
 * The copy that sits above the content on each route — the hero, the sidebar card and
 * the closing band.
 *
 * WHY THIS EXISTS SEPARATELY. Every page's `h1`, lead paragraph and outro copy was
 * hardcoded in its `page.tsx`, outside the content modules. Making only the data
 * editable would have left the owner unable to change a headline, which is the first
 * thing anyone wants to change. One row per route, looked up by the `route` key.
 *
 * `outro` is JSON because the closing band is two differently-shaped cards: /open-source
 * has a heading plus a CTA plus an aside, /services has a heading, a paragraph and two
 * link cards. Storing the union keeps both without a column for each.
 */
class PageHeader extends Model
{
    protected $table = 'content_page_headers';

    protected $fillable = [
        'route',
        'kicker',
        'title',
        'lead',
        'aside_label',
        'aside_text',
        'cta_label',
        'cta_href',
        'outro',
    ];

    protected function casts(): array
    {
        return [
            'outro' => 'array',
        ];
    }

    /**
     * The header copy for a route, or null when nothing has been written for it.
     *
     * Returns null rather than an empty model so the caller can fall back to the
     * bundled constants with a single `??`.
     */
    public static function forRoute(string $route): ?self
    {
        return static::query()->where('route', $route)->first();
    }

    /**
     * The frontend's `PageHeader` shape.
     *
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'kicker' => $this->kicker,
            'title' => $this->title,
            'lead' => $this->lead,
            'asideLabel' => $this->aside_label,
            'asideText' => $this->aside_text,
            'ctaLabel' => $this->cta_label,
            'ctaHref' => $this->cta_href,
            'outro' => $this->outro,
        ];
    }
}
