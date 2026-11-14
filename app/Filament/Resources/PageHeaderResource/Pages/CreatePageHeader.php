<?php

declare(strict_types=1);

namespace App\Filament\Resources\PageHeaderResource\Pages;

use App\Filament\Resources\PageHeaderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePageHeader extends CreateRecord
{
    protected static string $resource = PageHeaderResource::class;

    protected function getRedirectUrl(): string
    {
        // Straight back to the list. There are only four pages, and the usual next act
        // after adding copy for one is to look at the other three.
        return $this->getResource()::getUrl('index');
    }
}
