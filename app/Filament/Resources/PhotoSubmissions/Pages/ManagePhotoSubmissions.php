<?php

namespace App\Filament\Resources\PhotoSubmissions\Pages;

use App\Filament\Resources\PhotoSubmissions\PhotoSubmissionResource;
use App\Services\QueueWorkerStatus;
use Filament\Resources\Pages\ManageRecords;

class ManagePhotoSubmissions extends ManageRecords
{
    protected static string $resource = PhotoSubmissionResource::class;

    public function getSubheading(): ?string
    {
        return app(QueueWorkerStatus::class)->available() ? '处理服务已连接；采纳成功先进入草稿，未采纳文件 7 天后清理。' : '后台处理服务未连接，入库与文件清理会排队，请检查 Worker。';
    }
}
