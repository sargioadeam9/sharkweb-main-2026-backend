<?php

declare(strict_types=1);

namespace App\Filament\Resources\PageHeaderResource\Pages;

use App\Filament\Resources\PageHeaderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPageHeader extends EditRecord
{
    protected static string $resource = PageHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
