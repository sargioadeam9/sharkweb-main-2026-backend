<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /* Hashing is handled by the model's `hashed` cast — the same path the seeder
       and make:filament-user use, so no double-hashing guard is needed here. */

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }
}
