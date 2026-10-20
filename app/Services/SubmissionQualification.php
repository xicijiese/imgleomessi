<?php

namespace App\Services;

use App\Models\SubmissionApplication;
use App\Models\SubmissionEvent;
use App\Models\User;
use App\Notifications\UserCenterNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmissionQualification
{
    public function apply(User $user, string $reason): SubmissionApplication
    {
        return DB::transaction(function () use ($user, $reason): SubmissionApplication {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->role === 'user' && $user->status === 'active', 403);
            if (SubmissionApplication::query()->where('user_id', $user->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['reason' => '已有申请正在审核，请勿重复提交。']);
            }
            $application = SubmissionApplication::query()->create(['user_id' => $user->id, 'reason' => $reason]);
            SubmissionEvent::query()->create(['application_id' => $application->id, 'operator_id' => $user->id, 'action' => 'applied', 'note' => $reason]);

            return $application;
        });
    }

    public function review(SubmissionApplication $application, User $operator, string $decision, string $note): void
    {
        abort_unless($operator->fresh()->isAdministrator(), 403);
        DB::transaction(function () use ($application, $operator, $decision, $note): void {
            $user = User::query()->lockForUpdate()->findOrFail($application->user_id);
            $application = SubmissionApplication::query()->lockForUpdate()->findOrFail($application->id);
            $allowed = $application->status === 'pending' ? ['approved', 'rejected'] : ($application->status === 'approved' ? ['revoked'] : []);
            if (! in_array($decision, $allowed, true) || trim($note) === '') {
                throw ValidationException::withMessages(['note' => '申请状态已变化，或处理原因尚未填写。']);
            }
            if ($decision === 'approved') {
                if ($user->role !== 'user' || $user->status !== 'active') {
                    throw ValidationException::withMessages(['note' => '用户当前角色或状态不允许授予投稿资格。']);
                }
                $user->update(['role' => 'submitter']);
            }
            if ($decision === 'revoked' && SubmissionApplication::query()->where('user_id', $user->id)->where('id', '>', $application->id)->where('status', 'approved')->exists()) {
                throw ValidationException::withMessages(['note' => '已有更新的授权记录，请从最新记录处理资格。']);
            }
            if ($decision === 'revoked' && $user->role === 'submitter') {
                $user->update(['role' => 'user']);
            }
            $application->update(['status' => $decision, 'reviewed_by' => $operator->id, 'review_note' => $note, 'reviewed_at' => now()]);
            SubmissionEvent::query()->create(['application_id' => $application->id, 'operator_id' => $operator->id, 'action' => $decision, 'note' => $note]);
            $user->notify(new UserCenterNotification('submission', '投稿资格已更新', SubmissionApplication::STATUSES[$decision].'：'.$note, '/me/submissions', 'submission_application', $application->id));
        });
    }
}
