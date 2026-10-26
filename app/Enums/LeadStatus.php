<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Lead pipeline status (§Module A).
 *
 * Implements the Filament contracts so the admin table renders the correct
 * label, colour and icon without any per-column configuration.
 */
enum LeadStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Closed = 'closed';
    case Spam = 'spam';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Closed => 'Closed',
            self::Spam => 'Spam',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'warning',
            self::Qualified => 'success',
            self::Closed => 'gray',
            self::Spam => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::New => 'heroicon-o-sparkles',
            self::Contacted => 'heroicon-o-paper-airplane',
            self::Qualified => 'heroicon-o-check-badge',
            self::Closed => 'heroicon-o-archive-box',
            self::Spam => 'heroicon-o-shield-exclamation',
        };
    }

    /** Statuses that still need a human to act. */
    public static function openCases(): array
    {
        return [self::New, self::Contacted, self::Qualified];
    }
}
