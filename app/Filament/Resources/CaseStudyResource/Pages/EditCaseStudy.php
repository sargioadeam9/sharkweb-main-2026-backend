<?php

declare(strict_types=1);

namespace App\Filament\Resources\CaseStudyResource\Pages;

use App\Filament\Resources\CaseStudyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCaseStudy extends EditRecord
{
    protected static string $resource = CaseStudyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The case page is the thing being edited, so a way to see it matters more
            // here than on a list-like screen.
            Actions\Action::make('view_site')
                ->label('View case')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => rtrim((string) config('sharkweb.frontend_url'), '/')."/work/{$this->getRecord()->slug}/")
                ->openUrlInNewTab(),

            Actions\DeleteAction::make()
                ->modalDescription('The case page is hidden and its card comes off /work. It can be restored from the trashed list for 30 days.'),
        ];
    }

    /** See `CreateCaseStudy`: the same unwrapping has to run on edit. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CaseStudyResource::normaliseCase($data);
    }
}
