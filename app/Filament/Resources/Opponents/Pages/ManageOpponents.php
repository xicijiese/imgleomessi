<?php

namespace App\Filament\Resources\Opponents\Pages;

use App\Filament\Resources\Opponents\OpponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOpponents extends ManageRecords
{
    protected static string $resource = OpponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}