<?php

declare(strict_types=1);

namespace App\Filament\Resources\StatResource\Pages;

use App\Filament\Resources\StatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStat extends ListRecords
{
    protected static string $resource = StatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
