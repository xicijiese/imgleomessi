<?php

namespace App\Filament\Resources\SponsorshipOrders\Pages;

use App\Filament\Resources\SponsorshipOrders\SponsorshipOrderResource;
use App\Models\SponsorshipOrder;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageSponsorshipOrders extends ManageRecords
{
    protected static string $resource = SponsorshipOrderResource::class;

    public function getSubheading(): ?string
    {
        $query = SponsorshipOrder::where('channel', 'xunhu');
        $received = (clone $query)->whereNotNull('paid_at')->sum('amount_cents');
        $refunded = (clone $query)->where('status', 'refunded')->sum('amount_cents');

        return sprintf('真实收款 ¥%.2f · 已退款 ¥%.2f · 净支持 ¥%.2f（不含模拟数据、渠道手续费及银行结算）', $received / 100, $refunded / 100, ($received - $refunded) / 100);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('exportPayments')->label('导出真实订单')->icon(Heroicon::OutlinedArrowDownTray)->url(route('admin.payment-report'))];
    }
}
