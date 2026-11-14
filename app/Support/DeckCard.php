<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Builds the `DeckCardData` shape the site's 3D card components consume.
 *
 * WHY A SUPPORT CLASS. Two tables produce a deck card — the homepage's featured projects
 * and the card beside each client review — and they must produce byte-identical output or
 * the same card looks different depending on which band it is in. One composer, one answer.
 *
 * WHAT IS DERIVED AND WHY. `i`, `z`, `tilt` and `shift` are geometry, not content: they
 * make a stack of cards look hand-placed. They are computed from the record's position so
 * an editor adding a sixth project gets a card that sits in the pile instead of a card
 * with no tilt that overlaps its neighbour. The five values below are the ones the design
 * shipped with, in order, and they repeat if the list grows past five.
 *
 * THE TONES COLLAPSE TO ONE. The shipped cards set `brandTone`, `domainTone`, `backTone`
 * and `ctaTone` to the same value on every record, so the panel exposes a single `tone`
 * and this class fans it out. `frontBg` is separate because one of the five deliberately
 * pairs an ink-toned card with the yellow front.
 */
final class DeckCard
{
    /** @var list<string> */
    private const TILTS = ['-3.4deg', '2.6deg', '-1.8deg', '3.2deg', '-2.4deg'];

    /** @var list<string> */
    private const SHIFTS = ['-5%', '4%', '-2.5%', '6%', '-4%'];

    /**
     * @param  array<string, mixed>  $values  one record's content columns
     * @param  bool  $flat  true for a card that sits alone rather than in a stack
     * @return array<string, mixed>
     */
    public static function compose(array $values, int $index, bool $flat = false): array
    {
        $tone = self::tone($values['tone'] ?? 'accent');
        // Every card in the reviews carousel is flat and sits at z 1 — one card on screen
        // at a time, never a pile. The featured deck is the only stacked one.
        $slot = $flat ? 0 : $index % count(self::TILTS);

        // Tones are emitted bare (`green`), not as classes (`text-green`). Every card
        // component runs them through `text()` / `toneText()` in `components/tone.ts`,
        // which is what keeps Tailwind able to see the literal class strings.
        return [
            'i' => $flat ? 0 : $index,
            'tilt' => $flat ? '0deg' : self::TILTS[$slot],
            'shift' => $flat ? '0%' : self::SHIFTS[$slot],
            'z' => $flat ? 1 : $index + 1,
            'frontBg' => self::frontBg($values['front_bg'] ?? null, $tone),
            'brand' => (string) ($values['brand'] ?? 'THESHARKWEB.COM'),
            'brandTone' => $tone,
            'year' => (string) ($values['year_label'] ?? ''),
            'badge' => (bool) ($values['badge'] ?? false),
            'thumbs' => self::list($values['thumbs'] ?? null),
            'name' => (string) ($values['name'] ?? ''),
            'domain' => (string) ($values['domain'] ?? ''),
            'domainTone' => $tone,
            'stack' => (string) ($values['stack'] ?? ''),
            'result' => (string) ($values['result'] ?? ''),
            'resultTone' => $tone,
            'backBrand' => self::backBrand($values['back_brand'] ?? null, $index),
            'backTitle' => (string) ($values['back_title'] ?? ''),
            'backTone' => $tone,
            'backDesc' => (string) ($values['back_desc'] ?? ''),
            'chips' => self::list($values['chips'] ?? null),
            'facts' => self::list($values['facts'] ?? null),
            'specs' => self::specs($values['specs'] ?? null),
            'ctaHref' => (string) ($values['cta_href'] ?? '/work/'),
            'ctaTone' => $tone,
            'ctaText' => (string) ($values['cta_text'] ?? 'READ THE CASE STUDY →'),
        ];
    }

    /** Only the five tones the stylesheet actually defines. */
    private static function tone(mixed $value): string
    {
        $tone = is_string($value) ? $value : 'accent';

        return in_array($tone, ['accent', 'green', 'orange', 'magenta', 'ink'], true) ? $tone : 'accent';
    }

    /**
     * The front face's background utility.
     *
     * `front_bg` is stored as the bare name (`green-soft`) because that is what the
     * component concatenates onto `bg-`. Falling back to the tone keeps a record usable
     * even if the column was never filled in.
     */
    private static function frontBg(mixed $value, string $tone): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return match ($tone) {
            'green' => 'green-soft',
            'orange' => 'orange-soft',
            'magenta' => 'magenta-soft',
            'ink' => 'yellow-soft',
            default => 'blue-soft',
        };
    }

    /**
     * The number printed on the card's back.
     *
     * Optional on purpose: the shipped cards carry `# 002`, `# 003`, `# 005`, `# 006`,
     * `# 032` — references to project numbers that do not follow the list order. When the
     * field is left blank we fall back to the position, which is at least consistent.
     */
    private static function backBrand(mixed $value, int $index): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return '# '.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * JSON columns come back as arrays already, but a record created by hand in the panel
     * can hold anything. Always emit a list so the component can `.map()` it.
     *
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * The spec table is stored as `[{label, value}]` because that is what a Filament
     * repeater can edit, and emitted as `[[label, value]]` because that is what the
     * component destructures. Both shapes are accepted on the way in so a record imported
     * before this conversion still renders.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function specs(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            // Already a pair: [ "domain", "Recruitment" ]
            if (array_key_exists(0, $row) && array_key_exists(1, $row)) {
                $out[] = [(string) $row[0], (string) $row[1]];

                continue;
            }

            // Repeater shape: { label: "domain", value: "Recruitment" }
            $out[] = [(string) ($row['label'] ?? ''), (string) ($row['value'] ?? '')];
        }

        return $out;
    }
}
