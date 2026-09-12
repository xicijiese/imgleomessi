<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminAuditService
{
    public function record(User $actor, string $action, ?User $target = null, array $before = [], array $after = []): AdminAuditLog
    {
        abort_unless($actor->isAdministrator(), 403);

        /** @var Request|null $request */
        $request = app()->bound('request') ? request() : null;

        return AdminAuditLog::query()->create([
            'actor_user_id' => $actor->getKey(),
            'target_user_id' => $target?->getKey(),
            'action' => $action,
            'before_state' => $before ?: null,
            'after_state' => $after ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /** @return array<string, mixed> */
    public function userState(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'banned_until' => $user->banned_until?->toIso8601String(),
            'avatar_url' => $user->avatar_url,
        ];
    }
}
