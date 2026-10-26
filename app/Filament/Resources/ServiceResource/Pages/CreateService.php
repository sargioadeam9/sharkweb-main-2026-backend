<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getRedirectUrl(): string
    {
        // Back to the list. After adding one record the next act is usually to check
        // its position among the others, which only the list shows.
        return $this->getResource()::getUrl('index');
    }

    /**
     * Derive the FAQ anchors before the row is written.
     *
     * `ServicePage` renders `<a href="#{id}">` for every question, so a blank `id` is a
     * link to nowhere. Generating it from the question makes the field optional in
     * practice rather than a field that needs explaining.
     *
     * NOTE: the media collection is NOT handled here. Spatie's Filament field syncs its
     * own collection during the form save, so calling `addMediaFromDisk()` on top of it
     * would file the same upload twice.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ServiceResource::fillMissingAnchors($data);
    }
}
