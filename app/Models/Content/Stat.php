<?php

declare(strict_types=1);

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One figure in the stats bar under the hero: 12 years, 50+ clients, 250K users.
 *
 * WHY ITS OWN TABLE. These are the numbers a visitor uses to decide whether the agency is
 * real, and they are the ones most likely to go stale — "12 years" was written into
 * `StatsBar.tsx` and would still have said 12 in year fifteen. Five rows is also the
 * cheapest content screen in the panel: five fields and a tone.
 *
 * THE TONE IS THE COLOUR. The component renders `text-ink`, `text-accent`, `text-green`,
 * `text-orange` or `text-magenta` from this value; it is stored as the bare name so the
 * panel's select and the Tailwind class cannot disagree about the prefix.
 */
class Stat extends Model
{
    protected $table = 'content_stats';

    protected $fillable = [
        'label',
        'value',
        'suffix',
        'caption',
        'tone',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
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
     * The frontend's `STATS` entry, verbatim.
     *
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'label' => $this->label,
            'tone' => 'text-'.$this->tone,
            'value' => $this->value,
            'suffix' => $this->suffix ?? '',
            'caption' => $this->caption ?? '',
        ];
    }
}
