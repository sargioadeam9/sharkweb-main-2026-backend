<?php

declare(strict_types=1);

namespace App\Filament\Resources\OssProjectResource\Pages;

use App\Filament\Resources\OssProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOssProject extends EditRecord
{
    protected static string $resource = OssProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open')
                ->label('Open project')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => $this->getRecord()->href)
                ->openUrlInNewTab(),

            Actions\DeleteAction::make()
                ->modalDescription('The tile comes off /open-source. The uploaded screenshot stays in the media library.'),
        ];
    }

    /** See `CreateOssProject`: the accent pair is split here, the grid borders on the model. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return OssProjectResource::normaliseOss($data);
    }
}
