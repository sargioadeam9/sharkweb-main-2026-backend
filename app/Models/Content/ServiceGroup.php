<?php

declare(strict_types=1);

namespace App\Models\Content;

use App\Models\Concerns\RegistersMediaConversions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A discipline on /services — "Growth & media", "Web & product", and so on.
 *
 * The four groups are a fixed taxonomy in the design: the catalog card, its `tone` and
 * its numbered heading are all rendered from this record, and the services inside it are
 * the only variable part. So the table is small and the relationship carries the weight.
 */
class ServiceGroup extends Model implements HasMedia
{
    use InteractsWithMedia;
    use RegistersMediaConversions;

    protected $table = 'content_service_groups';

    protected $fillable = [
        'num',
        'key',
        'name',
        'tone',
        'blurb',
        'sort',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default')->singleFile();
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->registerSharkwebConversions('default');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'group_id')->orderBy('sort');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /**
     * The frontend's `ServiceGroup` shape.
     *
     * `services` is passed in rather than lazy-loaded so the whole catalog can be
     * assembled from one query in the controller — thirteen services across four groups
     * would otherwise be five queries and an N+1.
     *
     * @param  list<array<string, mixed>>  $services
     * @return array<string, mixed>
     */
    public function toFrontendArray(array $services = []): array
    {
        return [
            'num' => $this->num,
            'key' => $this->key,
            'name' => $this->name,
            'tone' => $this->tone,
            'blurb' => $this->blurb ?? '',
            'services' => $services,
        ];
    }
}
