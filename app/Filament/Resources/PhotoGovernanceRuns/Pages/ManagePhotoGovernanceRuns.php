<?php

namespace App\Filament\Resources\PhotoGovernanceRuns\Pages;

use App\Filament\Resources\PhotoDuplicates\PhotoDuplicateResource;
use App\Filament\Resources\PhotoGovernanceRuns\PhotoGovernanceRunResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePhotoGovernanceRuns extends ManageRecords
{
    protected static string $resource = PhotoGovernanceRunResource::class;

    public function mount(): void
    {
        $this->redirect(PhotoDuplicateResource::getUrl());
    }
}
