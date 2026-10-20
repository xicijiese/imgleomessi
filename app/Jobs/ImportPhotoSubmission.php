<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoSubmission;
use App\Models\ProcessingJob;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\LocalPhotoDerivativeService;
use App\Services\PhotoSimilarityService;
use App\Services\PhotoStorage;
use App\Services\PhotoSubmissionService;
use App\Services\QueueWorkerStatus;
use App\Services\SubmissionStorage;
use App\Services\TencentDataWanxiangService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ImportPhotoSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public string $submissionId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('submission:'.$this->submissionId))->shared()->expireAfter(150)->releaseAfter(15)];
    }

    public function handle(SubmissionStorage $temporary, PhotoStorage $library, PhotoSubmissionService $submissions): void
    {
        try {
            $this->import($temporary, $library, $submissions);
        } catch (Throwable $error) {
            try {
                $row = PhotoSubmission::query()->find($this->submissionId);
                if ($row) {
                    $temporary->discardUnadoptedAssets($row);
                }
            } catch (Throwable $cleanupError) {
                report($cleanupError);
            }
            throw $error;
        }
    }

    private function import(SubmissionStorage $temporary, PhotoStorage $library, PhotoSubmissionService $submissions): void
    {
        $row = PhotoSubmission::query()->findOrFail($this->submissionId);
        if ($row->status !== 'processing') {
            return;
        }
        if (! $row->reviewer?->canManageGalleryContent()) {
            throw new RuntimeException('审核人员权限已失效，请由有效审核人员重试。');
        }
        $contents = $temporary->contents($row);
        if (hash('sha256', $contents) !== $row->sha256) {
            throw new RuntimeException('暂存文件校验失败。');
        }
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$row->mime_type];
        $key = 'photos/originals/submissions/'.$row->asset_uuid.'.'.$extension;
        $row->update(['import_location' => $temporary->libraryLocation()]);
        $disk = $library->diskForKey();
        if (! $disk->put($key, $contents)) {
            throw new RuntimeException('正式图库写入失败。');
        }
        $staged = new Photo(['uuid' => $row->asset_uuid, 'original_key' => $key, 'width' => $row->width, 'height' => $row->height]);
        $derived = $library->activeDiskName() === 'cos'
            ? app(TencentDataWanxiangService::class)->generateDerivatives($staged)
            : app(LocalPhotoDerivativeService::class)->generate($staged);
        $hash = $row->perceptual_hash ?: app(PhotoSimilarityService::class)->hashForContents($contents);
        DB::transaction(function () use ($row, $key, $derived, $hash, $submissions): void {
            // 固定锁顺序：投稿人、投稿记录、图库记录。重复执行只确认一次采纳。
            $user = User::query()->lockForUpdate()->findOrFail($row->user_id);
            $current = PhotoSubmission::query()->lockForUpdate()->findOrFail($row->id);
            if ($current->status !== 'processing') {
                return;
            }
            $meta = $current->metadata;
            $photo = $current->replacement_photo_id
                ? Photo::query()->lockForUpdate()->findOrFail($current->replacement_photo_id)
                : new Photo(['uuid' => $current->asset_uuid]);
            $replacing = $photo->exists;
            if ($replacing && ($photo->width * $photo->height >= $current->width * $current->height || $photo->status === 'archived'
                || in_array($photo->copyright_status, ['restricted', 'remove_requested'], true))) {
                throw new RuntimeException('目标图片已变化，不能继续高清替换。');
            }
            if (PhotoAnalysisResult::query()->where('sha256_hash', $current->sha256)->where('photo_id', '!=', $photo->id ?? 0)->exists()) {
                throw new RuntimeException('相同图片已入库，请复核重复图片。');
            }
            if ($replacing) {
                $submissions->event($current, $current->reviewer, 'replacement_before', '高清替换前的原档案快照', $photo->getAttributes());
            } else {
                $photo->fill(['title' => $meta['title'] ?: $current->original_filename, 'description' => $meta['description'] ?? null,
                    'event_date' => $meta['event_date'] ?? null, 'source_url' => $meta['source_url'] ?? null,
                    'uploaded_by' => $current->reviewed_by, 'status' => 'draft', 'publish_after_processing' => false]);
            }
            $photo->fill([...$derived, 'original_key' => $key, 'original_filename' => $current->original_filename,
                'stored_filename' => basename($key), 'width' => $current->width, 'height' => $current->height,
                'mime_type' => $current->mime_type, 'file_size' => $current->file_size])->save();
            if (! $replacing) {
                $photo->categories()->sync($meta['category_ids']);
                $photo->tags()->sync($meta['tag_ids']);
                if (! empty($meta['album_id'])) {
                    $photo->albums()->sync([$meta['album_id']]);
                }
            }
            PhotoAnalysisResult::query()->updateOrCreate(['photo_id' => $photo->id], [
                'sha256_hash' => $current->sha256, 'perceptual_hash' => $hash, 'width' => $current->width, 'height' => $current->height,
                'mime_type' => $current->mime_type, 'file_size' => $current->file_size, 'processed_at' => now(), 'error_message' => null,
            ]);
            foreach (['metadata', 'hash', 'datawanxiang_derivatives'] as $type) {
                ProcessingJob::query()->create(['photo_id' => $photo->id, 'type' => $type, 'status' => 'done', 'processed_at' => now(), 'payload' => ['submission_id' => $current->id]]);
            }
            $current->update(['status' => 'accepted', 'photo_id' => $photo->id, 'accepted_at' => now(), 'cleanup_at' => now(), 'error_message' => null]);
            $submissions->event($current, $current->reviewer, 'imported', $replacing ? '高清替换成功，保留原档案关联' : '采纳入库成功，待人工发布');
            app(BadgeService::class)->evaluate($user);
            NotifySubmissionBatch::dispatch($current->batch_id, $user->id)->onConnection(app(QueueWorkerStatus::class)->connection())->delay(now()->addSeconds(15))->afterCommit();
        }, 3);
    }

    public function failed(?Throwable $error): void
    {
        PhotoSubmission::query()->whereKey($this->submissionId)->where('status', 'processing')->update([
            'status' => 'failed', 'error_message' => '入库失败，请联系审核人员处理或重试。暂存文件仍保留。',
        ]);
        $row = PhotoSubmission::query()->find($this->submissionId);
        if ($row) {
            try {
                app(SubmissionStorage::class)->discardUnadoptedAssets($row);
            } catch (Throwable $cleanupError) {
                report($cleanupError);
            }
            NotifySubmissionBatch::dispatch($row->batch_id, $row->user_id)->onConnection(app(QueueWorkerStatus::class)->connection())->delay(now()->addSeconds(15));
        }
    }
}
