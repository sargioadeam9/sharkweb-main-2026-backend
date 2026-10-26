<?php

declare(strict_types=1);

namespace App\Filament\Resources\TierResource\Pages;

use App\Filament\Resources\TierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTier extends ListRecords
{
    protected static string $resource = TierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
