<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeaturedCardResource\Pages;

use App\Filament\Resources\FeaturedCardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeaturedCard extends CreateRecord
{
    protected static string $resource = FeaturedCardResource::class;
}
