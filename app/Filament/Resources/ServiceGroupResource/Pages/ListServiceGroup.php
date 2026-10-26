<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceGroupResource\Pages;

use App\Filament\Resources\ServiceGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceGroup extends ListRecords
{
    protected static string $resource = ServiceGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
