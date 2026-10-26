<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeaturedCardResource\Pages;

use App\Filament\Resources\FeaturedCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeaturedCard extends ListRecords
{
    protected static string $resource = FeaturedCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
