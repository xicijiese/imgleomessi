<?php

namespace App\Filament\Resources\Albums\Pages;

use App\Filament\Resources\Albums\AlbumResource;
use App\Models\Album;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Arr;

class ManageAlbums extends ManageRecords
{
    protected static string $resource = AlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Album {
                    $categoryIds = AlbumResource::categoryIdsFromFormData($data);
                    $album = Album::create(Arr::except($data, AlbumResource::categoryFieldNamesForForm()));
                    $album->categories()->sync($categoryIds);

                    return $album;
                }),
        ];
    }
}
