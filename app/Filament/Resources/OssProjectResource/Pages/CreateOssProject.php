<?php

declare(strict_types=1);

namespace App\Filament\Resources\OssProjectResource\Pages;

use App\Filament\Resources\OssProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOssProject extends CreateRecord
{
    protected static string $resource = OssProjectResource::class;

    protected function getRedirectUrl(): string
    {
        // Back to the list. After adding one record the next act is usually to
        // check its position among the others, which only the list shows.
        return $this->getResource()::getUrl('index');
    }

    /**
     * Split the accent pair and default the alt text before the row is written.
     *
     * The grid's `cell` classes are NOT set here: the model regenerates them in its own
     * `saving` hook, which also covers drag-and-drop reordering, the bulk actions and the
     * importer. Doing it here as well would be a second place to keep in step.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return OssProjectResource::normaliseOss($data);
    }
}
