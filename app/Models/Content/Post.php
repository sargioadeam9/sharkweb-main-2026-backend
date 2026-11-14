<?php

declare(strict_types=1);

namespace App\Models\Content;

use App\Models\Concerns\RegistersMediaConversions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A newsletter issue.
 *
 * Soft-deleted, unlike the tiers: an article is something somebody wrote, and a
 * mis-click on a list screen must not destroy it. The Trash filter on the resource
 * is what makes the soft delete actually recoverable rather than merely invisible.
 *
 * `body` is the block builder — an ordered list of typed blocks (h2 / h3 / p / quote /
 * ul / ol / table). The block type is what `PostBody.tsx` switches on, so the frontend
 * contract is that list verbatim; `Block` in `lib/newsletter.ts` is the source of truth
 * and the Filament repeater's `t` field must offer exactly those seven values.
 */
class Post extends Model implements HasMedia
{
    use InteractsWithMedia;
    use RegistersMediaConversions;
    use SoftDeletes;

    protected $table = 'content_posts';

    protected $fillable = [
        'slug',
        'title',
        'category',
        'tone',
        'date',
        'read_time',
        'is_featured',
        'excerpt',
        'date_label',
        'takeaways',
        'toc',
        'body',
        'faq',
        'sort',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'takeaways' => 'array',
            'toc' => 'array',
            'body' => 'array',
            'faq' => 'array',
            'date' => 'date',
            'published_at' => 'datetime',
            'read_time' => 'integer',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        // The cover art is generated from the slug by `Cover.tsx`, so there is nothing to
        // upload today. The collection exists so a future hand-drawn cover has a home
        // without a migration.
        $this->addMediaCollection('cover')->singleFile();
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->registerSharkwebConversions('cover');
    }

    /** Readers only ever see published issues whose date has arrived. */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderByDesc('date')->orderByDesc('id');
    }

    /**
     * Normalise the block builder's output into the exact union `PostBody.tsx` switches on.
     *
     * TWO THINGS THE REPEATER CANNOT EXPRESS, both fixed here:
     *
     *  1. **H2 anchors.** The block builder types an `id`, but the contents list and the
     *     `<a href="#...">` links depend on it being unique and present. A blank one is
     *     derived from the heading text; a duplicate gets a numeric suffix, because two
     *     headings with the same anchor means the second is unreachable.
     *
     *  2. **Table rows.** A `table` block stores `head: string[]` and `rows: string[][]`.
     *     The repeater collects rows as one textarea with a line per cell, so `cells`
     *     (`"Name\nValue"`) has to be split back into an array. Splitting on `\n` rather
     *     than commas is what stops a cell containing a comma from breaking the row.
     *
     * @param  list<array<string, mixed>>|null  $blocks
     * @return list<array<string, mixed>>|null
     */
    public static function normaliseBlocks(?array $blocks): ?array
    {
        if ($blocks === null) {
            return null;
        }

        $seen = [];
        $out = [];

        foreach ($blocks as $block) {
            $type = $block['t'] ?? 'p';
            $clean = ['t' => $type];

            switch ($type) {
                case 'h2':
                    $html = (string) ($block['html'] ?? '');
                    $id = trim((string) ($block['id'] ?? ''));

                    if ($id === '') {
                        $id = Str::slug(html_entity_decode(strip_tags($html)));
                    }

                    if ($id === '') {
                        $id = 'section';
                    }

                    // Uniquify: a repeated anchor makes the later heading unlinkable.
                    $base = $id;
                    $n = 2;
                    while (isset($seen[$id])) {
                        $id = "{$base}-{$n}";
                        $n++;
                    }
                    $seen[$id] = true;

                    $clean['html'] = $html;
                    $clean['id'] = $id;
                    break;

                case 'h3':
                case 'p':
                case 'quote':
                    $clean['html'] = (string) ($block['html'] ?? '');
                    break;

                case 'ul':
                case 'ol':
                    // The simple repeater stores each item under its own key; the frontend
                    // wants a flat list of strings.
                    $clean['items'] = array_values(array_filter(array_map(
                        static fn ($item): string => is_array($item) ? (string) ($item['item'] ?? '') : (string) $item,
                        $block['items'] ?? [],
                    ), static fn (string $v): bool => trim($v) !== ''));
                    break;

                case 'table':
                    $head = array_values(array_filter(array_map(
                        static fn ($cell): string => is_array($cell) ? (string) ($cell['cell'] ?? '') : (string) $cell,
                        $block['head'] ?? [],
                    ), static fn (string $v): bool => trim($v) !== ''));

                    $rows = [];

                    foreach ($block['rows'] ?? [] as $row) {
                        // TWO SHAPES REACH HERE and both are legitimate:
                        //  - `{cells: "a\nb"}` from the repeater, which has to be split;
                        //  - `["a", "b"]` from a record the importer wrote, which is already
                        //    a row. Treating the second as the first would read `$row['cells']`
                        //    off a list, get null, and silently drop every table row — which
                        //    is exactly what editing an existing issue would have done.
                        if (is_array($row) && ! array_key_exists('cells', $row)) {
                            $cells = array_values(array_filter(
                                array_map(static fn ($cell): string => trim((string) $cell), $row),
                                static fn (string $v): bool => $v !== '',
                            ));
                        } else {
                            $raw = is_array($row) ? (string) ($row['cells'] ?? '') : (string) $row;

                            $cells = array_map('trim', preg_split('/\R/', $raw) ?: []);
                            $cells = array_values(array_filter($cells, static fn (string $v): bool => $v !== ''));
                        }

                        if ($cells !== []) {
                            $rows[] = $cells;
                        }
                    }

                    $clean['head'] = $head;
                    $clean['rows'] = $rows;
                    break;

                default:
                    // An unknown type would render nothing. Keep the text so the content is
                    // not lost, and let it fall to the `p` branch on the frontend.
                    $clean['t'] = 'p';
                    $clean['html'] = (string) ($block['html'] ?? '');
            }

            $out[] = $clean;
        }

        return $out;
    }

    /**
     * The inverse of `normaliseBlocks()` — stored JSON back into repeater shape.
     *
     * WHY BOTH DIRECTIONS EXIST. A `->simple()` repeater stores each item under its own key
     * (`{item: "…"}`, `{cells: "a\nb"}`), but the frontend wants flat strings and rows. So
     * the write path flattens. Filament then re-hydrates the form from the flattened JSON,
     * where the repeater cannot find `cells` on a `["a","b"]` row — it renders an empty
     * textarea, and the next save writes the empty value. Without this method, opening an
     * existing issue and pressing Save would erase every table in it.
     *
     * `toc` and `faq` are left alone: they are `->schema()` repeaters whose fields already
     * match the stored keys one-for-one.
     *
     * @param  list<array<string, mixed>>|null  $blocks
     * @return list<array<string, mixed>>|null
     */
    public static function blocksForForm(?array $blocks): ?array
    {
        if ($blocks === null) {
            return null;
        }

        return array_map(static function (array $block): array {
            $type = $block['t'] ?? 'p';

            if ($type === 'ul' || $type === 'ol') {
                $block['items'] = array_map(
                    static fn ($item): array => ['item' => is_array($item) ? (string) ($item['item'] ?? '') : (string) $item],
                    $block['items'] ?? [],
                );
            }

            if ($type === 'table') {
                $block['head'] = array_map(
                    static fn ($cell): array => ['cell' => is_array($cell) ? (string) ($cell['cell'] ?? '') : (string) $cell],
                    $block['head'] ?? [],
                );

                $block['rows'] = array_map(static function ($row): array {
                    // Already a repeater row — leave it, so filling twice is harmless.
                    if (is_array($row) && array_key_exists('cells', $row)) {
                        return $row;
                    }

                    $cells = is_array($row) ? $row : [$row];

                    return ['cells' => implode("\n", array_map(static fn ($c): string => (string) $c, $cells))];
                }, $block['rows'] ?? []);
            }

            return $block;
        }, $blocks);
    }

    /**
     * Takeaway bullets, form → stored.
     *
     * Same wrapper problem as list blocks: `->simple(Textarea::make('takeaway'))` yields
     * `[{takeaway: "…"}]` and the frontend renders `string[]`.
     *
     * @param  list<array<string, mixed>|string>|null  $takeaways
     * @return list<string>
     */
    public static function normaliseTakeaways(?array $takeaways): array
    {
        return array_values(array_filter(array_map(
            static fn ($item): string => trim(is_array($item) ? (string) ($item['takeaway'] ?? '') : (string) $item),
            $takeaways ?? [],
        ), static fn (string $v): bool => $v !== ''));
    }

    /**
     * Takeaway bullets, stored → form.
     *
     * @param  list<string>|null  $takeaways
     * @return list<array{takeaway: string}>
     */
    public static function takeawaysForForm(?array $takeaways): array
    {
        return array_map(
            static fn ($item): array => ['takeaway' => is_array($item) ? (string) ($item['takeaway'] ?? '') : (string) $item],
            $takeaways ?? [],
        );
    }

    /**
     * The frontend's `Post` shape.
     *
     * `dateLabel` is stored as typed rather than formatted from `date`, because the
     * source copy uses "October 7, 2026" and a `format()` call would impose a locale
     * the design did not choose. The form pre-fills it from `date` on create instead.
     *
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt ?? '',
            'category' => $this->category,
            'tone' => $this->tone,
            'date' => $this->date?->toDateString() ?? '',
            'dateLabel' => $this->date_label ?? $this->date?->format('F j, Y') ?? '',
            'readTime' => $this->read_time,
            'takeaways' => $this->takeaways ?? [],
            'toc' => $this->toc ?? [],
            'body' => $this->body ?? [],
            'faq' => $this->faq ?? [],
        ];
    }
}
