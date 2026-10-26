<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Smart lead categorisation (§14.10).
 *
 * The blueprint illustrates these with 🟢/🟡/⚪ but the semantic mapping is
 * hot = act now. Colours therefore run danger → warning → gray, which reads
 * correctly in a triage table.
 */
enum LeadScore: string implements HasColor, HasIcon, HasLabel
{
    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hot => '🔥 Hot',
            self::Warm => 'Warm',
            self::Cold => 'Cold',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hot => 'danger',
            self::Warm => 'warning',
            self::Cold => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Hot => 'heroicon-s-fire',
            self::Warm => 'heroicon-o-sun',
            // NOT 'heroicon-o-snowflake' — Heroicons ships no snowflake glyph, and
            // blade-icons throws SvgNotFound, which 500s the ENTIRE leads list the moment a
            // cold lead is on screen. The moon is the closest available "dormant" glyph and
            // is not used by any other enum here. Do not "restore" the snowflake.
            // Guarded by tests/Feature/IconIntegrityTest.php.
            self::Cold => 'heroicon-o-moon',
        };
    }

    /** Higher sorts first — a hot lead should top the table. */
    public function weight(): int
    {
        return match ($this) {
            self::Hot => 3,
            self::Warm => 2,
            self::Cold => 1,
        };
    }
}
