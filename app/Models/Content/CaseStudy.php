<?php

declare(strict_types=1);

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A documented engagement — /work/aryia-leather and its four siblings.
 *
 * No media collection: the case pages are typographic throughout, and the one place a
 * case contributes imagery (the testimonial band's deck card) draws it from the case's
 * own tone and metrics rather than a file.
 *
 * `headline` is `{ value, label }` and `metrics` is a list of the same shape. The
 * headline is separated from the list because it is rendered at a different size in
 * three places — the board card, the case hero and the prev/next footer.
 */
class CaseStudy extends Model
{
    use SoftDeletes;

    protected $table = 'content_cases';

    protected $fillable = [
        'slug',
        'num',
        'client',
        'vertical',
        'market',
        'tone',
        'year_label',
        'is_active',
        'initials',
        'services',
        'headline',
        'metrics',
        'summary',
        'note',
        'phases',
        'quote',
        'review_title',
        'review_body',
        'bg',
        'year_start',
        'year_end',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'headline' => 'array',
            'metrics' => 'array',
            'phases' => 'array',
            'quote' => 'array',
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
     * Fill in the fields that are derived rather than typed, whatever changed the record.
     *
     * A `saving` hook rather than logic in the Filament form mutator, because these three
     * are all restatements of other fields and every write path has to keep them in step:
     * the create/edit form, the bulk publish actions, drag-and-drop reordering and the
     * importer. Hooking the model covers all of them.
     */
    protected static function booted(): void
    {
        static::saving(function (self $case): void {
            if (blank($case->initials) && filled($case->client)) {
                $case->initials = $case->deriveInitials();
            }

            // A case whose number was left blank is numbered by its position. The column
            // defaults to `''`, not null, so this checks for blank rather than null. Done
            // here so the board card, the case page and the prev/next footer agree.
            if (blank($case->num)) {
                $case->num = str_pad((string) (((int) $case->sort) + 1), 3, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Initials for the outro tile grid. Derived on save when the form leaves it blank,
     * so an editor never has to think about it.
     */
    public function deriveInitials(): string
    {
        $words = preg_split('/\s+/', trim($this->client)) ?: [];
        $letters = '';

        foreach ($words as $word) {
            if ($word !== '') {
                $letters .= mb_strtoupper(mb_substr($word, 0, 1));
            }
        }

        return mb_substr($letters, 0, 3);
    }

    /**
     * The frontend's `CaseStudy` shape.
     *
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        $headline = $this->headline ?? ['value' => '', 'label' => ''];

        return [
            'slug' => $this->slug,
            'num' => $this->num,
            'client' => $this->client,
            'initials' => $this->initials ?: $this->deriveInitials(),
            'vertical' => $this->vertical,
            'market' => $this->market,
            'services' => $this->services ?? [],
            'headline' => $headline,
            'metrics' => $this->metrics ?? [],
            'summary' => $this->summary ?? '',
            'note' => $this->note ?? '',
            'phases' => $this->phases ?? [],
            'quote' => $this->quote,
            'reviewTitle' => $this->review_title ?? '',
            'reviewBody' => $this->review_body ?? '',
            'tone' => $this->tone,
            'bg' => $this->bg,
            'yearStart' => $this->year_start ?? '',
            'yearEnd' => $this->year_end ?? '',
            'yearLabel' => $this->year_label ?? '',
            'active' => $this->is_active,
        ];
    }

    /**
     * The board card on /work. Composed from the case rather than stored separately,
     * because every field on it is a restatement of something the case already holds —
     * two copies would be two places to forget to update.
     *
     * @return array<string, mixed>
     */
    public function toWorkCardArray(int $index, string $edge): array
    {
        $headline = $this->headline ?? ['value' => '', 'label' => ''];
        $quote = $this->quote;

        return [
            'className' => $edge,
            'categories' => $this->categoryKey(),
            'active' => $this->is_active ? '1' : '0',
            'pinned' => '0',
            'yearStart' => $this->year_start ?? '',
            'yearEnd' => $this->year_end ?? '',
            'href' => "/work/{$this->slug}/",
            'bg' => $this->bg,
            'barTone' => $this->tone,
            'num' => $this->num,
            'numTone' => $this->tone,
            'reviewTag' => $quote !== null,
            'year' => $this->year_label ?? '',
            'yearBold' => $this->is_active,
            'nda' => false,
            'thumbs' => [],
            'title' => $this->client,
            'domain' => mb_strtoupper($this->vertical),
            'domainTone' => $this->tone,
            'stack' => mb_strtoupper(implode(' · ', $this->services ?? [])),
            'result' => trim($headline['value'].' '.mb_strtolower($headline['label'])),
            'resultTone' => $this->tone,
            'noteText' => $quote ? '“'.$quote['text'].'”' : '',
            'noteBy' => $quote ? "— {$quote['name']}, {$quote['role']}" : '',
            'metric' => $this->headline,
        ];
    }

    /**
     * Which filter chip on /work this case belongs to.
     *
     * Derived from the services list rather than stored, so an editor who changes the
     * services cannot leave the case filed under a filter it no longer matches.
     */
    public function categoryKey(): string
    {
        $services = array_map('mb_strtolower', $this->services ?? []);

        foreach (['automation' => 'automation', 'funnel' => 'funnel'] as $needle => $key) {
            foreach ($services as $service) {
                if (str_contains($service, $needle)) {
                    return $key;
                }
            }
        }

        foreach ($services as $service) {
            if (str_contains($service, 'ads') || str_contains($service, 'media')) {
                return 'paid-media';
            }
        }

        return 'paid-media';
    }
}
