<?php

declare(strict_types=1);

namespace App\Models\Content;

use App\Support\DeckCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One project in the homepage's "# 02 — FEATURED PROJECTS" deck.
 *
 * WHY NOT A FLAG ON `content_cases`. The five cards here are Crawler, Retail Network,
 * EpinForce, Blaze Reviews and 99designs Contest Winners. The five rows in
 * `content_cases` are Aryia Leather, Firstway Global, ZIVO Audio, FishnFruit and WinPro
 * Digital — a different set entirely. There is nothing to flag: these projects have no
 * published case study yet, which is also why every card's CTA pointed at the bare
 * `/work/` index rather than at a page about itself.
 *
 * `cta_href` is editable per record so that changes the day a case is written, without
 * needing a code change.
 *
 * GEOMETRY IS NOT STORED. `tilt`, `shift`, `z` and `i` are derived from the record's
 * position by `DeckCard::compose()` — see that class for why.
 */
class FeaturedCard extends Model
{
    protected $table = 'content_featured_cards';

    protected $fillable = [
        'name',
        'brand',
        'domain',
        'year_label',
        'stack',
        'result',
        'badge',
        'back_brand',
        'back_title',
        'back_desc',
        'tone',
        'front_bg',
        'thumbs',
        'chips',
        'facts',
        'specs',
        'cta_text',
        'cta_href',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'badge' => 'boolean',
            'thumbs' => 'array',
            'chips' => 'array',
            'facts' => 'array',
            'specs' => 'array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontendArray(int $index = 0): array
    {
        return DeckCard::compose($this->only([
            'name', 'brand', 'domain', 'year_label', 'stack', 'result', 'badge',
            'back_brand', 'back_title', 'back_desc', 'tone', 'front_bg',
            'thumbs', 'chips', 'facts', 'specs', 'cta_text', 'cta_href',
        ]), $index);
    }
}
