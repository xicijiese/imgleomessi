<?php

namespace App\Jobs;

use App\Models\BadgeGrantBatch;
use App\Models\BadgeGrantRecipient;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\QueueWorkerStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessBadgeGrantBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $batchId) {}

    public function handle(BadgeService $badges): void
    {
        $batch = BadgeGrantBatch::query()->with(['badge', 'operator'])->findOrFail($this->batchId);
        if ($batch->status === 'completed') {
            return;
        }
        if (! $batch->operator?->isAdministrator()) {
            throw new RuntimeException('提交人的管理员权限已失效，不能继续批量发放。');
        }
        if (! $batch->badge->is_active) {
            throw new RuntimeException('勋章已停用，请启用后重试。');
        }
        $batch->update(['status' => 'running', 'error' => null]);
        $batch->recipients()->where('status', 'pending')->orderBy('id')->limit(100)->get()->each(function (BadgeGrantRecipient $recipient) use ($batch, $badges): void {
            DB::transaction(function () use ($recipient, $batch, $badges): void {
                $recipient = BadgeGrantRecipient::query()->whereKey($recipient->id)->lockForUpdate()->first();
                if (! $recipient || $recipient->status !== 'pending') {
                    return;
                }
                $user = User::query()->whereKey($recipient->user_id)->lockForUpdate()->first();
                $status = 'skipped';
                if ($user && $user->status === 'active' && ! $user->isBanned()) {
                    $existing = $user->userBadges()->where('badge_id', $batch->badge_id)->exists();
                    $awarded = $badges->award($user, $batch->badge, 'manual', $batch->operator, $batch->note);
                    $status = ! $existing && $awarded->exists ? 'awarded' : 'skipped';
                }
                $recipient->update(['status' => $status]);
            });
        });
        if ($batch->recipients()->where('status', 'pending')->exists()) {
            self::dispatch($batch->id)->onConnection(app(QueueWorkerStatus::class)->connection());
        } else {
            $batch->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        BadgeGrantBatch::query()->whereKey($this->batchId)->where('status', '!=', 'completed')->update([
            'status' => 'failed', 'error' => '发放失败，请检查队列日志并重试；已处理用户不会重复发放。',
        ]);
    }
}
