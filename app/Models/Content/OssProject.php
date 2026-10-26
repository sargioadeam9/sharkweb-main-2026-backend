<?php

declare(strict_types=1);

namespace App\Models\Content;

use App\Models\Concerns\RegistersMediaConversions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An open-source project tile on /open-source.
 *
 * THE `cell` COLUMN IS LAYOUT, NOT CONTENT, and it is here anyway. The grid's border
 * classes depend on the cell's index — the last cell of a row never carries a right
 * border — and the original markup spelled the strings out per index rather than
 * computing them. Recomputing would mean the frontend owning a rule about how many
 * columns the design uses, so the resource regenerates the string from `sort` on every
 * save instead. An editor never has to look at it.
 *
 * `img` (a bundled path) and `media` (an upload) coexist so an existing tile keeps
 * rendering from `/img/projects/...` until somebody uploads a replacement.
 */
class OssProject extends Model implements HasMedia
{
    use InteractsWithMedia;
    use RegistersMediaConversions;

    protected $table = 'content_oss_projects';

    /**
     * The three-column border pattern, by cell index. Lifted from the original
     * `PROJECTS` array so the rendered grid is byte-for-byte the same.
     *
     * @var list<string>
     */
    private const EDGE = [
        'relative border-b border-line sm:border-r sm:border-line lg:border-r lg:border-line',
        'relative border-b border-line lg:border-r lg:border-line',
        'relative border-b border-line',
        'relative border-b border-line sm:border-r sm:border-line lg:border-r lg:border-line',
        'relative border-b border-line lg:border-r lg:border-line',
        'relative border-b border-line',
    ];

    protected $fillable = [
        'num',
        'name',
        'href',
        'year',
        'label',
        'is_active',
        'tagline',
        'stack',
        'license',
        'alt',
        'img',
        'bar',
        'cell',
        'year_cls',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default')->singleFile();
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->registerSharkwebConversions('default');
    }

    /** Regenerate the grid's border classes for this record's position. */
    public function syncCell(): void
    {
        $this->cell = self::cellFromSort((int) $this->sort);
    }

    /**
     * The border classes for a given position in the grid.
     *
     * Static and public so the Filament create page can preview a border string before a
     * record exists, which is otherwise awkward on a form that has no model yet.
     */
    public static function cellFromSort(int $sort): string
    {
        return self::EDGE[$sort % count(self::EDGE)];
    }

    /**
     * Keep `cell` in step with `sort`, whatever changed it.
     *
     * A `saving` hook rather than a call in the form mutator, because `sort` is also
     * rewritten by drag-and-drop reordering on the list screen, by the bulk publish
     * actions and by the importer. Hooking the model covers all four; hooking the form
     * would only cover the one.
     */
    protected static function booted(): void
    {
        static::saving(function (self $project): void {
            $project->syncCell();
        });
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
     * The frontend's `Project` shape.
     *
     * `tone` is derived from `label` because the design uses one hue for both the left
     * bar and the accent text, and storing it twice is how the two drift apart.
     *
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        $media = $this->firstMedia('default');

        return [
            'num' => $this->num,
            'year' => $this->year,
            'name' => $this->name,
            'tagline' => $this->tagline ?? '',
            'stack' => $this->stack ?? '',
            'license' => $this->license ?? '',
            'href' => $this->href,
            'img' => $media?->getUrl() ?? $this->img ?? '',
            'alt' => $this->alt ?? $this->name,
            'cell' => $this->cell ?: self::cellFromSort((int) $this->sort),
            'bar' => $this->bar,
            'label' => $this->label,
            'yearCls' => $this->year_cls ?? 'meta font-bold',
        ];
    }
}
