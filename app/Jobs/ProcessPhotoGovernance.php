<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Models\PhotoGovernanceRun;
use App\Models\User;
use App\Services\PhotoSimilarityGovernance;
use App\Services\PhotoSimilarityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessPhotoGovernance implements ShouldQueue
{
    use Queueable;

    public int $tries = 20;

    public int $timeout = 120;

    public function __construct(public int $runId)
    {
        $this->onConnection(config('queue.default') === 'sync' ? 'database' : config('queue.default'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('photo-governance'))->shared()->releaseAfter(10)->expireAfter(150)];
    }

    public function handle(PhotoSimilarityGovernance $governance, PhotoSimilarityService $similarity): void
    {
        $run = PhotoGovernanceRun::query()->findOrFail($this->runId);
        if ($run->status === 'done') {
            return;
        }
        $operator = User::query()->find($run->operated_by);
        if (! $operator?->isAdministrator()) {
            $run->update(['status' => 'failed', 'error_message' => '发起人已无管理员权限，请由管理员重新提交。']);

            return;
        }
        $run->update(['status' => 'running', 'error_message' => null]);
        if ($run->type === 'groups') {
            if ($run->max_photo_id === null) {
                $run->update(['max_photo_id' => Photo::query()->max('id') ?? 0]);
            }
            // 每次只读取一小批原图，避免历史图库一次分析超过 Worker 超时。
            $photos = Photo::query()->where('status', '!=', 'archived')->where('id', '>', $run->last_photo_id)
                ->where('id', '<=', $run->max_photo_id)->orderBy('id')->limit(10)->get();
            foreach ($photos as $photo) {
                try {
                    $similarity->generateCandidates($photo);
                    $run->update(['last_photo_id' => $photo->id]);
                } catch (Throwable $exception) {
                    $run->update(['error_message' => '图片 #'.$photo->id.' 分析失败，请检查原图和存储配置后重试。']);
                    throw $exception;
                }
            }
            if ($photos->count() === 10) {
                self::dispatch($run->id);

                return;
            }
            $count = $governance->rebuildGroups();
        } else {
            $count = $governance->deduplicate($run->operated_by);
            $governance->rebuildGroups();
        }
        $run->update(['status' => 'done', 'result_count' => $count]);
    }

    public function failed(?Throwable $exception): void
    {
        $run = PhotoGovernanceRun::query()->find($this->runId);
        $run?->update(['status' => 'failed', 'error_message' => $run->error_message ?: '处理失败，请检查队列日志后重试。']);
    }
}
