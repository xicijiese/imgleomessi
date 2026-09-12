<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUserManagementService
{
    public const ROLES = [
        'user' => '普通用户',
        'editor' => '编辑员',
        'admin' => '管理员',
    ];

    public function __construct(private readonly AdminAuditService $audit) {}

    public function changeRole(User $target, User $operator, string $role): bool
    {
        $this->guard($target, $operator);

        if (! array_key_exists($role, self::ROLES)) {
            throw ValidationException::withMessages(['role' => '用户角色无效。']);
        }

        if ($target->role === $role) {
            return true;
        }

        if ($target->contributorProfile?->isActive()) {
            throw ValidationException::withMessages(['role' => '该用户仍是档案共建者，请先撤销共建者身份后再调整角色。']);
        }

        if ($target->role === 'admin' && $role !== 'admin' && $this->activeAdminCount() <= 1) {
            throw ValidationException::withMessages(['role' => '不能撤销系统中最后一名有效管理员。']);
        }

        $before = $this->audit->userState($target);
        $target->forceFill(['role' => $role])->save();
        $this->audit->record($operator, 'user.role_changed', $target, $before, $this->audit->userState($target));

        return true;
    }

    public function enable(User $target, User $operator): bool
    {
        $this->guard($target, $operator);

        return $this->setStatus($target, $operator, 'active', 'user.enabled');
    }

    public function disable(User $target, User $operator): bool
    {
        $this->guard($target, $operator);

        return $this->setStatus($target, $operator, 'disabled', 'user.disabled');
    }

    public function ban(User $target, User $operator, ?string $reason = null, ?string $note = null, mixed $until = null): bool
    {
        $this->guard($target, $operator);
        $before = $this->audit->userState($target);
        $updated = $target->ban($reason, $note, $until);

        if ($updated) {
            $this->audit->record($operator, 'user.banned', $target, $before, $this->audit->userState($target));
        }

        return $updated;
    }

    public function unban(User $target, User $operator, ?string $note = null): bool
    {
        $this->guard($target, $operator);
        $before = $this->audit->userState($target);
        $updated = $target->unban($note);

        if ($updated) {
            $this->audit->record($operator, 'user.unbanned', $target, $before, $this->audit->userState($target));
        }

        return $updated;
    }

    public function resetPassword(User $target, User $operator, string $password): void
    {
        $this->guard($target, $operator);
        $before = $this->audit->userState($target);

        $target->forceFill(['password' => $password])->save();
        $this->invalidateSessions($target);

        $this->audit->record($operator, 'user.password_reset_by_admin', $target, $before, [
            'session_version' => $target->fresh()?->session_version,
        ]);
    }

    public function revokeSessions(User $target, User $operator): void
    {
        $this->guard($target, $operator);
        $before = ['session_version' => $target->session_version];
        $this->invalidateSessions($target);

        $this->audit->record($operator, 'user.sessions_revoked', $target, $before, [
            'session_version' => $target->fresh()?->session_version,
        ]);
    }

    private function invalidateSessions(User $target): void
    {
        $target->increment('session_version');

        try {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $target->getKey())
                ->delete();
        } catch (QueryException) {
            // Redis 等非数据库会话驱动不依赖该表，session_version 仍会使旧会话失效。
        }
    }

    public function setStatus(User $target, User $operator, string $status, string $action): bool
    {
        if (! array_key_exists($status, User::STATUSES)) {
            throw ValidationException::withMessages(['status' => '用户状态无效。']);
        }

        $before = $this->audit->userState($target);
        $updated = $target->forceFill([
            'status' => $status,
            'banned_until' => $status === 'active' ? null : $target->banned_until,
        ])->save();

        if ($updated) {
            $this->audit->record($operator, $action, $target, $before, $this->audit->userState($target));
        }

        return $updated;
    }

    private function guard(User $target, User $operator): void
    {
        if (! $operator->isAdministrator()) {
            throw new AuthorizationException('只有有效管理员可以管理用户。');
        }

        if ($target->is($operator)) {
            throw ValidationException::withMessages(['user' => '不能在用户管理中修改当前管理员自己的账号。']);
        }
    }

    private function activeAdminCount(): int
    {
        return User::query()
            ->where('role', 'admin')
            ->where('status', 'active')
            ->count();
    }
}
