<?php

namespace App\Filament\Resources\SearchRecommendations\Pages;

use App\Filament\Resources\SearchRecommendations\SearchRecommendationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSearchRecommendations extends ManageRecords
{
    protected static string $resource = SearchRecommendationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
