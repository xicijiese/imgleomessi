<?php

namespace App\Filament\Resources\SensitiveWords\Pages;

use App\Filament\Resources\SensitiveWords\SensitiveWordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSensitiveWords extends ManageRecords
{
    protected static string $resource = SensitiveWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
