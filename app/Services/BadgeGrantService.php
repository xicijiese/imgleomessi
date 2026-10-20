<?php

namespace App\Services;

use App\Jobs\ProcessBadgeGrantBatch;
use App\Models\Badge;
use App\Models\BadgeGrantBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BadgeGrantService
{
    public function audience(string $audience, array $userIds = []): Builder
    {
        return User::query()->where('status', 'active')
            ->when($audience !== 'all', fn (Builder $query) => $query->whereIn('id', $userIds));
    }

    public function create(Badge $badge, User $operator, string $audience, array $userIds, ?string $note): BadgeGrantBatch
    {
        abort_unless($operator->isAdministrator(), 403);
        if (! $badge->is_active || ! in_array($audience, ['all', 'selected'], true)) {
            throw ValidationException::withMessages(['audience' => '请选择已启用的勋章和有效发放范围。']);
        }

        return DB::transaction(function () use ($badge, $operator, $audience, $userIds, $note): BadgeGrantBatch {
            // 锁定勋章，防止重复点击生成同一勋章的并行发放任务。
            Badge::query()->whereKey($badge->id)->lockForUpdate()->firstOrFail();
            if (BadgeGrantBatch::query()->where('badge_id', $badge->id)->whereIn('status', ['pending', 'running'])->exists()) {
                throw ValidationException::withMessages(['audience' => '该勋章已有排队或处理中的发放任务，请先查看处理进度。']);
            }
            if (! $this->audience($audience, $userIds)->exists()) {
                throw ValidationException::withMessages(['user_ids' => '发放范围内没有状态正常的用户。']);
            }
            $batch = BadgeGrantBatch::query()->create([
                'badge_id' => $badge->id, 'operator_id' => $operator->id,
                'audience' => $audience, 'note' => $note,
            ]);
            // 一次生成接收者快照，任务排队期间新注册的用户不会被加入。
            DB::table('badge_grant_recipients')->insertUsing(
                ['batch_id', 'user_id', 'status'],
                $this->audience($audience, $userIds)->selectRaw("? as batch_id, id, 'pending' as status", [$batch->id])->toBase(),
            );
            ProcessBadgeGrantBatch::dispatch($batch->id)->onConnection(app(QueueWorkerStatus::class)->connection())->afterCommit();

            return $batch;
        });
    }

    public function retry(BadgeGrantBatch $batch, User $operator): void
    {
        abort_unless($operator->isAdministrator(), 403);
        $updated = BadgeGrantBatch::query()->whereKey($batch->id)->where('status', 'failed')
            ->update(['status' => 'pending', 'error' => null]);
        if ($updated) {
            ProcessBadgeGrantBatch::dispatch($batch->id)->onConnection(app(QueueWorkerStatus::class)->connection());
        }
    }
}
