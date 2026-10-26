<?php

declare(strict_types=1);

namespace App\Filament\Resources\TierResource\Pages;

use App\Filament\Resources\TierResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTier extends CreateRecord
{
    protected static string $resource = TierResource::class;

    protected function getRedirectUrl(): string
    {
        // Back to the list. After adding one record the next act is usually to
        // check its position among the others, which only the list shows.
        return $this->getResource()::getUrl('index');
    }
}
