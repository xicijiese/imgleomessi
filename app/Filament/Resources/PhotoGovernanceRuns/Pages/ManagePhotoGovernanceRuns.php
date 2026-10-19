<?php

namespace App\Filament\Resources\PhotoGovernanceRuns\Pages;

use App\Filament\Resources\PhotoGovernanceRuns\PhotoGovernanceRunResource;
use App\Filament\Resources\PhotoSimilarityGroups\PhotoSimilarityGroupResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePhotoGovernanceRuns extends ManageRecords
{
    protected static string $resource = PhotoGovernanceRunResource::class;

    public function mount(): void
    {
        $this->redirect(PhotoSimilarityGroupResource::getUrl(parameters: ['tab' => 'runs']));
    }
}
