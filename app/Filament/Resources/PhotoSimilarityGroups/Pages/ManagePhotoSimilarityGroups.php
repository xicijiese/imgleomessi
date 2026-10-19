<?php

namespace App\Filament\Resources\PhotoSimilarityGroups\Pages;

use App\Filament\Resources\PhotoDeduplicationLogs\PhotoDeduplicationLogResource;
use App\Filament\Resources\PhotoGovernanceRuns\PhotoGovernanceRunResource;
use App\Filament\Resources\PhotoSimilarityCandidates\PhotoSimilarityCandidateResource;
use App\Filament\Resources\PhotoSimilarityGroups\PhotoSimilarityGroupResource;
use App\Jobs\ProcessPhotoGovernance;
use App\Models\PhotoDeduplicationLog;
use App\Models\PhotoGovernanceRun;
use App\Services\QueueWorkerStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

class ManagePhotoSimilarityGroups extends ManageRecords
{
    protected static string $resource = PhotoSimilarityGroupResource::class;

    public function getTabs(): array
    {
        return [
            'groups' => Tab::make('图片分组'),
            'candidates' => Tab::make('逐对审核'),
            'runs' => Tab::make('处理进度'),
            'logs' => Tab::make('归档记录'),
        ];
    }

    public function getSubheading(): ?string
    {
        return app(QueueWorkerStatus::class)->available()
            ? '后台处理服务已连接。分析生成分组后，可在本页逐对审核；人工排除会立即同步分组。'
            : '后台处理服务未连接，排队任务暂不会执行。请启动本地开发服务（composer dev）或联系管理员检查队列服务。';
    }

    protected function makeTable(): Table
    {
        $table = parent::makeTable();

        return match ($this->activeTab) {
            'candidates' => PhotoSimilarityCandidateResource::table($table)->query(PhotoSimilarityCandidateResource::getEloquentQuery()),
            'runs' => PhotoGovernanceRunResource::table($table)->query(PhotoGovernanceRun::query()),
            'logs' => PhotoDeduplicationLogResource::table($table)->query(PhotoDeduplicationLog::query()),
            default => $table->poll('5s'),
        };
    }

    public function updatedActiveTab(): void
    {
        if (! array_key_exists($this->activeTab, $this->getTabs())) {
            $this->activeTab = 'groups';
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
            Action::make('groups')->label('分析并重新分组')->requiresConfirmation()
                ->modalDescription('异步读取所有未归档图片并更新相似候选与分组，不修改图片发布状态。历史图库较大时需要等待队列处理。')
                ->action(fn () => $this->queueRun('groups')),
            Action::make('deduplicate')->label('精确重复图自动归档')->color('warning')->requiresConfirmation()
                ->modalDescription('仅处理已分析且 SHA-256 完全一致的图片。优先保留已发布图片，其次保留最早入库记录；其余归档，不删除文件或合并互动。可在“重复图归档记录”中恢复为草稿。')
                ->action(fn () => $this->queueRun('deduplicate')),
        ];
    }

    private function queueRun(string $type): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        if (PhotoGovernanceRun::query()->where('type', $type)->whereIn('status', ['pending', 'running'])->exists()) {
            Notification::make()->title('已有同类任务排队或处理中，请勿重复提交')->warning()->send();

            return;
        }
        $run = PhotoGovernanceRun::query()->create(['type' => $type, 'operated_by' => auth()->id()]);
        ProcessPhotoGovernance::dispatch($run->id);
        $notification = Notification::make();
        if (app(QueueWorkerStatus::class)->available()) {
            $notification->title('任务已提交，请切换“处理进度”查看结果')->success();
        } else {
            $notification->title('任务已排队，但后台处理服务未连接')->body('服务连接后会自动执行，无需重复提交。')->warning();
        }
        $notification->send();
    }
}
