<?php

namespace App\Filament\Resources\UserLoginHistories\Pages;

use App\Filament\Resources\UserLoginHistories\UserLoginHistoryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageUserLoginHistories extends ManageRecords
{
    protected static string $resource = UserLoginHistoryResource::class;
}