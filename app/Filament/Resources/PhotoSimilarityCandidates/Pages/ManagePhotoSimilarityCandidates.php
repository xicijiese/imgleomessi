<?php

namespace App\Filament\Resources\PhotoSimilarityCandidates\Pages;

use App\Filament\Resources\PhotoSimilarityCandidates\PhotoSimilarityCandidateResource;
use App\Filament\Resources\PhotoSimilarityGroups\PhotoSimilarityGroupResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePhotoSimilarityCandidates extends ManageRecords
{
    protected static string $resource = PhotoSimilarityCandidateResource::class;

    public function mount(): void
    {
        $this->redirect(PhotoSimilarityGroupResource::getUrl(parameters: [
            'tab' => 'candidates',
            'filters' => request()->query('filters', request()->query('tableFilters', [])),
        ]));
    }
}
