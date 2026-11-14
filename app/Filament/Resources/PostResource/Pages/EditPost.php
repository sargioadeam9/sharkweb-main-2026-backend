<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Content\Post;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Reading the issue as a reader would is the usual reason to leave this screen.
            Actions\Action::make('view_site')
                ->label('View issue')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => rtrim((string) config('sharkweb.frontend_url'), '/')."/newsletter/{$this->getRecord()->slug}/")
                ->openUrlInNewTab(),

            Actions\DeleteAction::make()
                ->modalDescription('The issue is hidden from the site. It can be restored from the trashed list for 30 days.'),
        ];
    }

    /**
     * Reshape the stored body into what the repeaters expect, before the form is filled.
     *
     * THE STEP THAT MAKES EDITING SAFE. `body` is stored flattened (`items: string[]`,
     * `rows: string[][]`) but the repeaters need their wrappers back (`{item}`,
     * `{cells}`). Without this, a table row arrives as `["a","b"]`, the repeater finds no
     * `cells` key, renders an empty textarea — and the next save writes the empty value,
     * quietly deleting the table. Same for `takeaways`.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['body'] = Post::blocksForForm($data['body'] ?? null);
        $data['takeaways'] = Post::takeawaysForForm($data['takeaways'] ?? null);

        return $data;
    }

    /** See `CreatePost`: the same body normalisation has to run on edit. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['body'] = Post::normaliseBlocks($data['body'] ?? null);
        $data['takeaways'] = Post::normaliseTakeaways($data['takeaways'] ?? null);

        return $data;
    }
}
