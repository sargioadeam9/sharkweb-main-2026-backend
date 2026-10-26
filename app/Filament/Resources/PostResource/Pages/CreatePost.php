<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Content\Post;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function getRedirectUrl(): string
    {
        // Back to the list. After adding one record the next act is usually to
        // check its position among the others, which only the list shows.
        return $this->getResource()::getUrl('index');
    }

    /**
     * Rebuild the article body into the shape `PostBody.tsx` switches on.
     *
     * The block-builder repeater is friendlier than the stored format: headings get
     * their anchor derived, list/table cells are entered as free text. `normaliseBlocks`
     * converts that back to `{t, html, id}` / `{t, items}` / `{t, head, rows}` so the
     * frontend never sees a shape it does not understand.
     *
     * `takeaways` needs the same unwrapping for the same reason — see the model.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['body'] = Post::normaliseBlocks($data['body'] ?? null);
        $data['takeaways'] = Post::normaliseTakeaways($data['takeaways'] ?? null);

        return $data;
    }
}
