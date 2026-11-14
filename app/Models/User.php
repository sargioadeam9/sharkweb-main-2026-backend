<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\CausesActivity;

/**
 * Backoffice operator.
 *
 * Implements `FilamentUser` because Filament only skips the access check when
 * APP_ENV=local — in production an unguarded model would let *any* authenticated user
 * into the admin panel.
 */
class User extends Authenticatable implements FilamentUser
{
    // Operators are the "causer" of every backoffice change; without this trait the
    // activity log's causer_id column stays null and the "who did this" question dies.
    use CausesActivity;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * True when this account may create and remove other accounts, and may reach the
     * Maintenance pages (backups, restore, junk cleaner).
     *
     * Role first — that is the designed control. The `sharkweb.maintenance.super_admins`
     * email list stays as a fallback so the pre-role env var keeps working (and so the
     * owner can grant a superadmin by email without touching the database).
     */
    public function isSuperAdmin(): bool
    {
        if ($this->role === UserRole::Superadmin) {
            return true;
        }

        $allowed = array_filter(array_map('trim', explode(',', (string) config('sharkweb.maintenance.super_admins'))));

        return in_array($this->email, $allowed, true);
    }

    /**
     * Gate access to the admin panel.
     *
     * Both roles (superadmin and staff) are operators: accounts are only ever created by
     * a superadmin via the Users page (or the seeder / make:filament-user on a fresh
     * install), and there is no public registration route. Staff see everything except
     * the Maintenance pages and the Users page — those are gated on `isSuperAdmin()`.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
