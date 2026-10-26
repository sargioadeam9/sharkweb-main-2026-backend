<?php

declare(strict_types=1);

namespace App\Filament\Resources\OssProjectResource\Pages;

use App\Filament\Resources\OssProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOssProject extends ListRecords
{
    protected static string $resource = OssProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
