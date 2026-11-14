<?php

declare(strict_types=1);

namespace App\Filament\Support;

/**
 * The design system's four accent tones, shared by every content resource.
 *
 * WHY A HELPER: the tone is a key the frontend maps to a Tailwind class (see
 * `components/tone.ts`), not a colour. It appears on service groups, services, case
 * studies and posts, and each of those resources needs the same option list, the same
 * badge colours and the same "what does this tone look like" hint. Spelling the array
 * out five times is how one screen ends up offering a tone the others do not.
 *
 * `ink` and `sub` are included because case studies use them for the neutral and
 * "everything under NDA" cards — those are real tones in the design, not fallbacks.
 */
final class Tone
{
    /**
     * Tone key => the design system's own name for it.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'accent' => 'Blue (accent)',
        'green' => 'Green',
        'orange' => 'Orange',
        'magenta' => 'Magenta',
        'ink' => 'Ink (neutral)',
    ];

    /**
     * The four identity tones, for records that should not be neutral — a service group
     * with an ink heading would read as disabled.
     *
     * @var array<string, string>
     */
    public const ACCENTS = [
        'accent' => 'Blue (accent)',
        'green' => 'Green',
        'orange' => 'Orange',
        'magenta' => 'Magenta',
    ];

    /**
     * Map a tone to a Filament badge/icon colour, so a record reads the same on the list
     * screen as it does on the site.
     */
    public static function color(?string $tone): string
    {
        return match ($tone) {
            'accent' => 'info',
            'green' => 'success',
            'orange' => 'warning',
            'magenta' => 'danger',
            default => 'gray',
        };
    }

    /**
     * The four `*-soft` background treatments, keyed by tone.
     *
     * These are what a case study's `bg` column holds — `orange-soft`, `green-soft`,
     * `blue-soft`, `magenta-soft`, `yellow-soft` — plus `paper` for the neutral card.
     *
     * @var array<string, string>
     */
    public const BACKGROUNDS = [
        'paper' => 'Paper (neutral)',
        'orange-soft' => 'Orange tint',
        'green-soft' => 'Green tint',
        'blue-soft' => 'Blue tint',
        'magenta-soft' => 'Magenta tint',
        'yellow-soft' => 'Yellow tint',
    ];
}
