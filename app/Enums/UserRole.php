<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The two classes of backoffice operator.
 *
 * `superadmin` is the owner class: it may create and remove accounts, and it is the only
 * class that can reach the Maintenance pages (backups, restore, junk cleaner) — places
 * that destroy state. `staff` is everyone else: full panel access for the day job, but
 * no account management and no destructive maintenance.
 *
 * THE COLUMN HAS NO THIRD VALUE ON PURPOSE. Every new role multiplies what has to be
 * re-tested in every gate; add one only when a real person needs it.
 */
enum UserRole: string implements HasColor, HasIcon, HasLabel
{
    case Superadmin = 'superadmin';
    case Staff = 'staff';

    public function getLabel(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Staff => 'Staff',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Superadmin => 'danger',
            self::Staff => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Superadmin => 'heroicon-o-shield-check',
            self::Staff => 'heroicon-o-user',
        };
    }
}
