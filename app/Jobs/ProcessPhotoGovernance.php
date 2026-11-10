<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Models\PhotoGovernanceRun;
use App\Models\PhotoSimilarityCandidate;
use App\Models\User;
use App\Services\PhotoProcessingService;
use App\Services\PhotoSimilarityGovernance;
use App\Services\PhotoSimilarityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessPhotoGovernance implements ShouldQueue
{
    use DetectsConcurrencyErrors;
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
        // 旧推荐/分组任务安全结束，不再计算前台推荐或生成历史分组。
        if (in_array($run->type, ['recommendations', 'groups'], true)) {
            $run->update(['status' => 'done', 'result_count' => 0, 'error_message' => '旧相似推荐功能已停用；前台已改为同标签随机推荐。']);

            return;
        }
        $operator = User::query()->find($run->operated_by);
        if (! $operator?->isAdministrator()) {
            $run->update(['status' => 'failed', 'error_message' => '发起人已无管理员权限，请由管理员重新提交。']);

            return;
        }
        $run->update(['status' => 'running', 'error_message' => null]);
        if ($run->type === 'duplicates') {
            if ($run->max_photo_id === null) {
                $run->update(['max_photo_id' => Photo::query()->max('id') ?? 0]);
            }
            if ($run->total_count === null) {
                $scope = Photo::query()->where('status', '!=', 'archived')->where('id', '<=', $run->max_photo_id);
                $run->update([
                    'total_count' => (clone $scope)->count(),
                    'processed_count' => (clone $scope)->where('id', '<=', $run->last_photo_id)->count(),
                ]);
            }
            // 每次只读取一小批原图，避免历史图库一次分析超过 Worker 超时。
            $photos = Photo::query()->where('status', '!=', 'archived')->where('id', '>', $run->last_photo_id)
                ->where('id', '<=', $run->max_photo_id)->orderBy('id')->limit(10)->get();
            foreach ($photos as $photo) {
                $progress = ['last_photo_id' => $photo->id, 'processed_count' => $run->processed_count + 1];
                try {
                    if (blank($photo->analysisResult?->sha256_hash)) {
                        $processing = app(PhotoProcessingService::class);
                        $hashJob = $processing->process($processing->createJob($photo, 'hash'));
                        if ($hashJob->status !== 'done') {
                            throw new \RuntimeException($hashJob->error_message);
                        }
                        $photo->refresh();
                    }
                    $similarity->generateCandidates($photo);
                } catch (Throwable $exception) {
                    report($exception);
                    $failures = $run->failures ?? [];
                    $failures[] = [
                        'photo_id' => $photo->id,
                        'title' => $photo->title,
                        'original_filename' => $photo->original_filename,
                        'stored_filename' => $photo->stored_filename,
                        'reason' => $this->imageFailureReason($exception),
                    ];
                    $progress += ['failed_count' => $run->failed_count + 1, 'failures' => $failures];
                }
                // 成功和单图失败均推进游标；一次写入，避免重试时重复统计。
                $run->update($progress);
            }
            if ($photos->count() === 10) {
                self::dispatch($run->id)->onConnection($this->connection)->onQueue($this->queue);

                return;
            }
            $results = PhotoSimilarityCandidate::query()
                ->where('photo_id', '<=', $run->max_photo_id)->where('candidate_photo_id', '<=', $run->max_photo_id);
            $failedIds = array_column($run->failures ?? [], 'photo_id');
            if ($failedIds !== []) {
                $results->whereNotIn('photo_id', $failedIds)->whereNotIn('candidate_photo_id', $failedIds);
            }
            $count = $results->where('distance', '<=', PhotoSimilarityService::DISTANCE_THRESHOLD)
                ->whereHas('photo', fn ($photos) => $photos->where('status', '!=', 'archived'))
                ->whereHas('candidatePhoto', fn ($photos) => $photos->where('status', '!=', 'archived'))->count();
        } else {
            // 已排队的旧自动归档任务也不再修改图片，避免更新后意外执行旧规则。
            $run->update([
                'status' => 'done',
                'result_count' => 0,
                'error_message' => '自动归档已停用，请在图片查重页面人工选择处理。',
            ]);

            return;
        }
        $run->update(['status' => 'done', 'result_count' => $count]);
    }

    private function imageFailureReason(Throwable $exception): string
    {
        $message = $exception->getMessage();

        // 不把供应商响应、文件路径或可能含凭据的异常原文直接显示到弹窗。
        return match (true) {
            $this->causedByConcurrencyError($exception) => '保存匹配结果失败：数据库暂时繁忙，自动重试后仍未成功；原图无需修改，请稍后重新检查。',
            str_contains($message, '缺少原图存储 Key') => '该档案没有关联原图文件，无法读取图片内容。',
            str_contains($message, '原图文件不存在') => '原图文件不存在或已被移走，无法读取图片内容。',
            str_contains($message, '无法读取图片内容') => '图片内容无法读取，文件可能已损坏或格式不受支持。',
            str_contains($message, 'AccessDenied') || str_contains($message, 'Permission denied') => '无法访问原图，请检查图片存储的访问权限。',
            default => '图片分析失败，请检查文件和存储访问情况；详细原因已记录到后台日志。',
        };
    }

    public function failed(?Throwable $exception): void
    {
        $run = PhotoGovernanceRun::query()->find($this->runId);
        if ($run && $run->status !== 'done') {
            $run->update(['status' => 'failed', 'error_message' => $run->error_message ?: '任务执行中断，请检查后台处理服务和日志后重试。']);
        }
    }
}
