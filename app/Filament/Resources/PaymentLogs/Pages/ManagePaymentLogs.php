<?php

namespace App\Filament\Resources\PaymentLogs\Pages;

use App\Filament\Resources\PaymentLogs\PaymentLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentLogs extends ManageRecords
{
    protected static string $resource = PaymentLogResource::class;
}
