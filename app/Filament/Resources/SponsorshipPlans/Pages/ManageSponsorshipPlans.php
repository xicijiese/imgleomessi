<?php

namespace App\Filament\Resources\SponsorshipPlans\Pages;

use App\Filament\Resources\SponsorshipPlans\SponsorshipPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSponsorshipPlans extends ManageRecords
{
    protected static string $resource = SponsorshipPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
