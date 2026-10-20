<?php

namespace App\Jobs;

use App\Models\PhotoSubmission;
use App\Services\PhotoSubmissionService;
use App\Services\SubmissionStorage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class CleanPhotoSubmission implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public string $submissionId) {}

    public function uniqueId(): string
    {
        return $this->submissionId;
    }

    public function handle(SubmissionStorage $storage, PhotoSubmissionService $service): void
    {
        DB::transaction(function () use ($storage, $service): void {
            $row = PhotoSubmission::query()->lockForUpdate()->find($this->submissionId);
            if (! $row || $row->cleaned_at || ! $row->cleanup_at || $row->cleanup_at->isFuture()
                || ! in_array($row->status, ['uploading', 'temporary', 'rejected', 'withdrawn', 'accepted'], true)) {
                return;
            }
            $storage->delete($row);
            if (in_array($row->status, ['temporary', 'uploading'], true)) {
                $row->status = 'withdrawn';
            }
            $row->update(['cleaned_at' => now(), 'error_message' => null]);
            $service->event($row, null, 'cleaned', '仅清理该投稿的私有暂存文件');
        });
    }

    public function failed(?Throwable $error): void
    {
        PhotoSubmission::query()->whereKey($this->submissionId)->whereNull('cleaned_at')->update(['error_message' => '暂存文件清理失败，系统将再次重试。']);
    }
}
