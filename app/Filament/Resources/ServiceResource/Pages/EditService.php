<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Checking the copy in place is the most common reason to leave this screen.
            Actions\Action::make('view_site')
                ->label('View page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => rtrim((string) config('sharkweb.frontend_url'), '/')."/services/{$this->getRecord()->slug}/")
                ->openUrlInNewTab(),

            Actions\DeleteAction::make()
                ->modalDescription('The catalog entry and this page both go. Its URL will start returning a 404.'),
        ];
    }

    /** See `CreateService`: the same anchor derivation has to run on edit. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ServiceResource::fillMissingAnchors($data);
    }
}
