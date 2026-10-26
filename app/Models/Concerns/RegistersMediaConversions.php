<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Registers the four standard WebP derivatives.
 *
 * Sizes come from config('sharkweb.media.conversions') so the admin panel, the API
 * and any future image tooling all agree on one set of dimensions.
 *
 * Every conversion is marked `nonQueued()`. That is not a micro-optimisation: the
 * production target is shared cPanel hosting with no supervisor process
 * (docs/BACKEND_MVP_PLAN.md §4), so a queued conversion would sit in the jobs table
 * forever and the upload would appear to succeed while producing no derivatives.
 *
 * NOTE ON NAMING: this deliberately does NOT declare `registerMediaConversions()`.
 * Spatie's `InteractsWithMedia` trait already declares that method, and PHP rejects the
 * collision outright ("Trait method ... has not been applied ... because of collision").
 * Each model therefore implements `registerMediaConversions()` itself and delegates here,
 * which also leaves room for a model to add collection-specific conversions later.
 */
trait RegistersMediaConversions
{
    /**
     * Register every configured conversion on the current model.
     *
     * `$onlyCollection` exists because conversions are image operations. A model that also
     * carries non-image media — a product's downloadable .zip, say — would otherwise have
     * WebP derivatives attempted against it on upload, which fails. Passing a collection
     * name scopes the conversions to the one that actually holds pictures.
     */
    protected function registerSharkwebConversions(?string $onlyCollection = null): void
    {
        foreach ((array) config('sharkweb.media.conversions', []) as $name => $size) {
            $conversion = $this->addMediaConversion($name)
                ->width((int) ($size['width'] ?? 0))
                ->height((int) ($size['height'] ?? 0))
                ->format('webp')
                ->quality((int) config('sharkweb.media.quality', 82))
                ->sharpen(10)
                ->nonQueued();

            if ($onlyCollection !== null) {
                $conversion->performOnCollections($onlyCollection);
            }
        }
    }

    /**
     * Absolute URLs for every registered conversion, keyed by conversion name.
     * The API Resources use this so the frontend never has to guess a file path.
     *
     * @return array<string, string>
     */
    public function conversionUrls(string $collection = 'default'): array
    {
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return [];
        }

        $urls = [];

        foreach (array_keys((array) config('sharkweb.media.conversions', [])) as $name) {
            // getUrl() returns '' when the conversion has not been generated yet,
            // so fall back to the original rather than handing the frontend a blank src.
            $urls[$name] = $media->getUrl($name) ?: $media->getUrl();
        }

        return $urls;
    }

    /**
     * The original file's URL plus every derivative.
     * Used by API Resources that need the full image payload in one call.
     *
     * @return array{original: string|null, conversions: array<string, string>}|null
     */
    public function imagePayload(string $collection = 'default'): ?array
    {
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return null;
        }

        return [
            'original' => $media->getUrl(),
            'conversions' => $this->conversionUrls($collection),
        ];
    }

    /** Media item for a collection, or null. Exposed for the API Resources. */
    public function firstMedia(string $collection = 'default'): ?Media
    {
        return $this->getFirstMedia($collection);
    }

    /**
     * Derive the frontend's media `base` from an uploaded file.
     *
     * `ad-creative-1200.webp` -> `ad-creative`. The frontend appends the size and extension
     * itself (`/media/blog/<base>-<width>.webp`), so a stored size suffix would double up.
     * Falls back to the supplied value when nothing has been uploaded.
     */
    protected function mediaBaseFrom(?Media $media, ?string $fallback = null): ?string
    {
        if (! $media) {
            return $fallback;
        }

        $name = pathinfo($media->file_name, PATHINFO_FILENAME);

        return preg_replace('/-\d{2,4}$/', '', $name) ?: $name;
    }

    /**
     * The frontend's `{ base, alt, caption? }` image shape, plus real conversion URLs.
     *
     * `urls` is an addition to the frontend interface, not a replacement: `base` keeps the
     * existing contract working while the real Spatie URLs let the frontend migrate to
     * uploaded media without a breaking change.
     *
     * @return array<string, mixed>|null
     */
    public function frontendImagePayload(string $collection, ?string $fallbackBase, ?string $fallbackAlt): ?array
    {
        $media = $this->getFirstMedia($collection);

        $base = $this->mediaBaseFrom($media, $fallbackBase);
        $alt = $media?->getCustomProperty('alt') ?: $fallbackAlt;

        if ($base === null) {
            return null;
        }

        $payload = ['base' => $base, 'alt' => $alt];

        $caption = $media?->getCustomProperty('caption');

        if (filled($caption)) {
            $payload['caption'] = $caption;
        }

        if ($media) {
            $payload['urls'] = $this->conversionUrls($collection);
            $payload['url'] = $media->getUrl();
        }

        return $payload;
    }
}
