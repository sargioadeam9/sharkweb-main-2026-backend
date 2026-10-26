<?php

declare(strict_types=1);

namespace App\Models\Content;

use App\Support\DeckCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One client testimonial in the homepage's "# 03 — CLIENT REVIEWS" carousel.
 *
 * WHAT IS EDITABLE AND WHAT IS NOT. Eighteen of these shipped inside `lib/data.ts`, each
 * with a nested `card` object of twenty-five fields. Most of those fields were design:
 * tilt, shift, z, four separate tone slots, a front background. Only the words, the
 * person, the avatar and the handful of card facts are content, so only those are columns
 * here. The rest is rebuilt by `DeckCard::compose()` from the record's position, which
 * means an editor cannot flatten the stack or break a tone by editing a field they should
 * never have been shown.
 *
 * `role_tone` stays its own column rather than following `card_tone`: the shipped data
 * uses them independently (a green role label over an accent-toned card is a real
 * combination in the set).
 */
class Review extends Model
{
    protected $table = 'content_reviews';

    protected $fillable = [
        'title',
        'quote',
        'body',
        'avatar',
        'name',
        'role',
        'role_tone',
        'review_url',
        'card_name',
        'card_back_brand',
        'card_brand',
        'card_domain',
        'card_year_label',
        'card_stack',
        'card_result',
        'card_badge',
        'card_tone',
        'card_front_bg',
        'card_back_title',
        'card_back_desc',
        'card_thumbs',
        'card_chips',
        'card_facts',
        'card_specs',
        'card_cta_text',
        'card_cta_href',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'card_badge' => 'boolean',
            'card_thumbs' => 'array',
            'card_chips' => 'array',
            'card_facts' => 'array',
            'card_specs' => 'array',
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
     * The frontend's `ReviewData`, with the nested card rebuilt from position.
     *
     * @return array<string, mixed>
     */
    public function toFrontendArray(int $index = 0): array
    {
        $card = DeckCard::compose([
            // The card names the project, not the reviewer: "Retail Network",
            // "StudyBooster", "SV Geo". In the shipped set it never equals `name`.
            'name' => $this->card_name,
            'brand' => $this->card_brand,
            'domain' => $this->card_domain,
            'year_label' => $this->card_year_label,
            'stack' => $this->card_stack,
            'result' => $this->card_result,
            'badge' => $this->card_badge,
            'back_brand' => $this->card_back_brand ?: null,
            'back_title' => $this->card_back_title !== '' ? $this->card_back_title : $this->title,
            'back_desc' => $this->card_back_desc,
            'tone' => $this->card_tone,
            'front_bg' => $this->card_front_bg,
            'thumbs' => $this->card_thumbs,
            'chips' => $this->card_chips,
            'facts' => $this->card_facts,
            'specs' => $this->card_specs,
            'cta_text' => $this->card_cta_text,
            'cta_href' => $this->card_cta_href,
            // The carousel shows one card at a time, so it is never part of the pile.
        ], 0, flat: true);

        return [
            'title' => $this->title,
            'quote' => $this->quote,
            'body' => $this->body ?? '',
            'avatar' => $this->avatar ?? '',
            'avatarAlt' => $this->name,
            'name' => $this->name,
            'role' => $this->role,
            // Bare tone, not a class: `Reviews.tsx` runs it through `toneText()`.
            'roleTone' => $this->role_tone,
            'reviewUrl' => $this->review_url ?? '',
            'card' => $card,
        ];
    }
}
