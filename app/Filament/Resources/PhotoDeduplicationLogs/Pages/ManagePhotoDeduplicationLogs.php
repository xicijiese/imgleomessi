<?php

namespace App\Filament\Resources\PhotoDeduplicationLogs\Pages;

use App\Filament\Resources\PhotoDeduplicationLogs\PhotoDeduplicationLogResource;
use App\Filament\Resources\PhotoSimilarityGroups\PhotoSimilarityGroupResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePhotoDeduplicationLogs extends ManageRecords
{
    protected static string $resource = PhotoDeduplicationLogResource::class;

    public function mount(): void
    {
        $this->redirect(PhotoSimilarityGroupResource::getUrl(parameters: ['tab' => 'logs']));
    }
}
