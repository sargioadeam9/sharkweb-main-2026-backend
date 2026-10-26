<?php

declare(strict_types=1);

namespace App\Models\Content;

use App\Models\Concerns\RegistersMediaConversions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One service page — /services/meta-ads and its twelve siblings.
 *
 * THE NESTED FIELDS ARE JSON, and that is deliberate. `sections` alone holds three
 * sections, each with a list of items that are one of three shapes (row / step / case);
 * `stat` is a fixed-shape block; `faq` holds five question-answer pairs. Modelling each
 * as its own table would have produced five tables and a form that saves across all of
 * them, for data that is only ever read as an entire page and written as an entire page.
 *
 * `review_case` is a slug, not a foreign key, on purpose: the testimonial band pulls a
 * whole case study, and the frontend already resolves that by slug through
 * `caseReview()`. A dangling slug falls back to the first quoted case rather than
 * breaking the page.
 */
class Service extends Model implements HasMedia
{
    use InteractsWithMedia;
    use RegistersMediaConversions;

    protected $table = 'content_services';

    protected $fillable = [
        'slug',
        'group_id',
        'name',
        'desc',
        'tone',
        'crumb',
        'crumb_href',
        'h1',
        'lead',
        'chips',
        'meta',
        'stat',
        'sections',
        'faq',
        'final',
        'article',
        'review_case',
        'review_label',
        'sort',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'chips' => 'array',
            'meta' => 'array',
            'stat' => 'array',
            'sections' => 'array',
            'faq' => 'array',
            'final' => 'array',
            'article' => 'array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        // The promo block's screenshot. Single file: a service page shows one image.
        $this->addMediaCollection('article')->singleFile();
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->registerSharkwebConversions('article');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ServiceGroup::class, 'group_id');
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
     * The service's card in the catalog grid.
     *
     * @return array<string, mixed>
     */
    public function toCardArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'desc' => $this->desc ?? '',
            'tone' => $this->tone,
        ];
    }

    /**
     * The full frontend `ServicePageData` shape.
     *
     * An uploaded image wins over the bundled path, so the promo block keeps rendering
     * from `/img/...` until the owner uploads a replacement.
     *
     * @param  array<string, mixed>|null  $reviews  Resolved by the controller from `review_case`.
     * @return array<string, mixed>
     */
    public function toFrontendArray(?array $reviews = null): array
    {
        $article = $this->article;

        if ($article !== null) {
            $media = $this->firstMedia('article');

            if ($media !== null) {
                $article['img'] = $media->getUrl();
            }
        }

        return [
            'slug' => $this->slug,
            'meta' => $this->meta ?? ['title' => $this->name, 'description' => ''],
            'crumb' => $this->crumb ?? "/ SERVICES / {$this->name}",
            'crumbHref' => $this->crumb_href,
            'h1' => $this->h1 ?? $this->name,
            'lead' => $this->lead ?? '',
            'chips' => $this->chips ?? [],
            'stat' => $this->stat ?? [],
            'sections' => $this->sections ?? [],
            'reviews' => $reviews ?? ['label' => '# TESTIMONIAL', 'items' => []],
            'article' => $article,
            'faq' => $this->faq ?? [],
            'final' => $this->final ?? [],
        ];
    }
}
