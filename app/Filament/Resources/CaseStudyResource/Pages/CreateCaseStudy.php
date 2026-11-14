<?php

declare(strict_types=1);

namespace App\Filament\Resources\CaseStudyResource\Pages;

use App\Filament\Resources\CaseStudyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCaseStudy extends CreateRecord
{
    protected static string $resource = CaseStudyResource::class;

    protected function getRedirectUrl(): string
    {
        // Back to the list. After adding one record the next act is usually to
        // check its position among the others, which only the list shows.
        return $this->getResource()::getUrl('index');
    }

    /**
     * Unwrap the repeater output and drop a blank quote before the row is written.
     *
     * `initials` and `num` are NOT filled here — the model derives both in its own
     * `saving` hook, which also covers the bulk actions and drag-and-drop reordering. See
     * `CaseStudyResource::normaliseCase()` for what this one does.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CaseStudyResource::normaliseCase($data);
    }
}
