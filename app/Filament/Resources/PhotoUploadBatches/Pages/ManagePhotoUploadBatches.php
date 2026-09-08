<?php

namespace App\Filament\Resources\PhotoUploadBatches\Pages;

use App\Filament\Resources\PhotoUploadBatches\PhotoUploadBatchResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePhotoUploadBatches extends ManageRecords
{
    protected static string $resource = PhotoUploadBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
