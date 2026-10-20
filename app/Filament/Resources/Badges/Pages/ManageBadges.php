<?php

namespace App\Filament\Resources\Badges\Pages;

use App\Filament\Resources\Badges\BadgeResource;
use App\Services\QueueWorkerStatus;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

class ManageBadges extends ManageRecords
{
    protected static string $resource = BadgeResource::class;

    public function getTabs(): array
    {
        return ['badges' => Tab::make('勋章配置'), 'batches' => Tab::make('发放进度'), 'events' => Tab::make('操作记录')];
    }

    public function getSubheading(): ?string
    {
        return app(QueueWorkerStatus::class)->available()
            ? '后台处理服务已连接。停用勋章保留已获得记录；纪念日按北京时间登录访问自动获取。'
            : '后台处理服务未连接，批量发放将暂时排队。请启动队列 Worker 后查看发放进度。';
    }

    protected function makeTable(): Table
    {
        $table = parent::makeTable();

        return match ($this->activeTab) {
            'batches' => BadgeResource::batchesTable($table),
            'events' => BadgeResource::eventsTable($table),
            default => $table,
        };
    }

    public function updatedActiveTab(): void
    {
        if (! array_key_exists($this->activeTab, $this->getTabs())) {
            $this->activeTab = 'badges';
        }
        $this->tableSearch = '';
        $this->tableSort = null;
        $this->tableFilters = null;
        $this->resetTable();
        parent::updatedActiveTab();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn (): bool => $this->activeTab === null || $this->activeTab === 'badges'),
        ];
    }
}
