<?php

namespace App\Filament\Resources\Albums\Pages;

use App\Filament\Resources\Albums\AlbumResource;
use App\Models\Album;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class ManageAlbums extends ManageRecords
{
    protected static string $resource = AlbumResource::class;

    protected function applySortingToTableQuery(Builder $query): Builder
    {
        if ($this->isTableReordering()) {
            return $query->orderBy('sort_order')->orderByDesc('created_at')->orderByDesc('id');
        }

        return parent::applySortingToTableQuery($query);
    }

    public function toggleTableReordering(): void
    {
        if (! $this->isTableReordering) {
            $this->resetTableFiltersForm();
            $this->resetTableSearch();
            $this->resetTableColumnSearches();
            $this->tableSort = null;
        }
        $this->isTableReordering = ! $this->isTableReordering;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth('5xl')
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->using(function (array $data): Album {
                    $categoryIds = AlbumResource::categoryIdsFromFormData($data);
                    $album = Album::create(Arr::except($data, AlbumResource::categoryFieldNamesForForm()));
                    $album->categories()->sync($categoryIds);

                    return $album;
                }),
        ];
    }
}
