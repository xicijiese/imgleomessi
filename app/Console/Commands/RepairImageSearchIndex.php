<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Services\PhotoSimilarityService;
use App\Services\PhotoStorage;
use Illuminate\Console\Command;

class RepairImageSearchIndex extends Command
{
    protected $signature = 'media:repair-search-index {--apply : 备份后补建缺失的检索指纹} {--local-only : 仅允许在本地存储模式运行}';

    protected $description = '盘点公开图片缺失的以图搜图指纹，默认只读';

    public function handle(PhotoSimilarityService $similarity, PhotoStorage $storage): int
    {
        if ($this->option('local-only') && $storage->activeDiskName() !== 'public') {
            $this->error('当前不是本地存储模式，已停止。');

            return self::FAILURE;
        }
        $query = Photo::query()->published()->whereNotIn('copyright_status', ['restricted', 'remove_requested'])
            ->whereDoesntHave('analysisResult', fn ($q) => $q->whereNotNull('perceptual_hash')->where('perceptual_hash', '!=', ''));
        $this->line('存储：'.$storage->activeDiskName().'；缺失检索指纹的公开图片：'.$query->count());
        if (! $this->option('apply')) {
            $this->line('仅检查。备份后使用 --apply；COS模式会读取源文件，可能产生流量费用。');

            return self::SUCCESS;
        }
        $done = 0;
        $failed = 0;
        foreach ($query->lazyById(100) as $photo) {
            try {
                $similarity->updatePerceptualHash($photo);
                $done++;
            } catch (\Throwable $exception) {
                report($exception);
                $this->warn('图片 #'.$photo->id.' 补建失败，请检查应用日志。');
                $failed++;
            }
        }
        $this->info('已补建：'.$done.'；失败：'.$failed.'。图片发布状态、相似候选及原图不变。');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
