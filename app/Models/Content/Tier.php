<?php

declare(strict_types=1);

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A commitment tier in "Pick the size of commitment" on /services.
 *
 * Smallest of the content models: five display fields, a bullet list and a CTA. It is
 * its own table rather than a slice of page settings because the count is the thing the
 * owner most wants to vary — the three-column grid is a design decision, not a rule.
 *
 * The section's own heading ("Pick the size of commitment", the # 03 kicker and the
 * supporting paragraph) is NOT here: it belongs to the page, so it lives on the
 * `content_page_headers` row for `services`.
 */
class Tier extends Model
{
    protected $table = 'content_tiers';

    protected $fillable = [
        'name',
        'price',
        'unit',
        'is_popular',
        'is_active',
        'blurb',
        'points',
        'cta',
        'href',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'array',
            'is_popular' => 'boolean',
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
     * The frontend's `Tier` shape — the component's local type, verbatim.
     *
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'name' => $this->name,
            'blurb' => $this->blurb ?? '',
            'price' => $this->price,
            'unit' => $this->unit,
            'points' => $this->points ?? [],
            'cta' => $this->cta ?? 'GET STARTED',
            'href' => $this->href ?? '/contact/',
            // Always present so the frontend can test the key rather than its value —
            // an absent key and a false one read the same in JS but mean different things.
            'popular' => $this->is_popular,
        ];
    }
}
