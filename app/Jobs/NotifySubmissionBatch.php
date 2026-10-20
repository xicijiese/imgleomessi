<?php

namespace App\Jobs;

use App\Models\PhotoSubmission;
use App\Models\SubmissionEvent;
use App\Models\User;
use App\Notifications\UserCenterNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class NotifySubmissionBatch implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $batchId, public int $userId) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $user = User::query()->lockForUpdate()->find($this->userId);
            if (! $user) {
                return;
            }
            $rows = PhotoSubmission::query()->where('user_id', $user->id)->where('batch_id', $this->batchId)->orderBy('id')->get();
            if ($rows->contains('status', 'processing')) {
                return;
            }
            $fingerprint = hash('sha256', $rows->map(fn ($row) => $row->id.':'.$row->status)->implode(','));
            if (SubmissionEvent::query()->where('action', 'batch_notified')->where('note', $fingerprint)->exists()) {
                return;
            }
            SubmissionEvent::query()->create(['submission_id' => $rows->first()->id, 'action' => 'batch_notified', 'note' => $fingerprint]);
            $user->notify(new UserCenterNotification('submission', '图片投稿处理进度', '本批已采纳 '.$rows->where('status', 'accepted')->count().' 张，未采纳 '.$rows->where('status', 'rejected')->count().' 张，入库失败 '.$rows->where('status', 'failed')->count().' 张。请查看逐张处理结果。', '/me/submissions', 'photo_submission'));
        });
    }
}
