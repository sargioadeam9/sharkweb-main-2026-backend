<?php

declare(strict_types=1);

namespace App\Filament\Resources\StatResource\Pages;

use App\Filament\Resources\StatResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStat extends CreateRecord
{
    protected static string $resource = StatResource::class;
}
