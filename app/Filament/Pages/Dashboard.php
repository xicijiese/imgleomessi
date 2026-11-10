<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\GalleryOverview;
use Filament\Widgets\AccountWidget;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function getWidgets(): array
    {
        return [GalleryOverview::class, AccountWidget::class];
    }
}
