<?php

namespace App\Services;

use App\Models\ContributorProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ContributorService
{
    public function grant(User $target, User $operator, array $data = []): ContributorProfile
    {
        $this->ensureAdministrator($operator);

        return DB::transaction(function () use ($target, $operator, $data): ContributorProfile {
            $profile = ContributorProfile::query()->firstOrNew([
                'user_id' => $target->id,
            ]);

            $needsRoleSnapshot = ! $profile->exists || ! $profile->isActive();
            if ($needsRoleSnapshot) {
                $profile->role_before_grant = $target->role;
                $profile->role_granted_by_contributor = $target->role === 'user';
            }

            $profile->display_name = array_key_exists('display_name', $data)
                ? $data['display_name']
                : ($profile->display_name ?: $target->name);
            $profile->bio = array_key_exists('bio', $data)
                ? $data['bio']
                : $profile->bio;
            $profile->contribution_focus = array_key_exists('contribution_focus', $data)
                ? $data['contribution_focus']
                : $profile->contribution_focus;
            $profile->is_public = array_key_exists('is_public', $data)
                ? (bool) $data['is_public']
                : ($profile->exists ? (bool) $profile->is_public : false);
            $profile->granted_at = now();
            $profile->granted_by = $operator->id;
            $profile->revoked_at = null;
            $profile->revoked_by = null;
            $profile->save();

            if ($profile->role_granted_by_contributor && $target->role === 'user') {
                $target->forceFill(['role' => 'editor'])->save();
            }

            return $profile;
        });
    }

    public function revoke(User $target, User $operator): bool
    {
        $this->ensureAdministrator($operator);

        return DB::transaction(function () use ($target, $operator): bool {
            $profile = $target->contributorProfile()->first();

            if (! $profile || ! $profile->isActive()) {
                return false;
            }

            $profile->forceFill([
                'is_public' => false,
                'revoked_at' => now(),
                'revoked_by' => $operator->id,
            ])->save();

            $target->refresh();

            if ($profile->role_granted_by_contributor && $target->role === 'editor') {
                $target->forceFill([
                    'role' => $profile->role_before_grant ?: 'user',
                ])->save();
            }

            return true;
        });
    }

    public function updateProfile(User $target, User $operator, array $data): ContributorProfile
    {
        $this->ensureAdministrator($operator);

        $profile = $target->contributorProfile()->first();

        if (! $profile || ! $profile->isActive()) {
            throw (new ModelNotFoundException())->setModel(ContributorProfile::class, [$target->id]);
        }

        $profile->update(Arr::only($data, [
            'display_name',
            'bio',
            'contribution_focus',
            'is_public',
            'sort_order',
        ]));

        return $profile->refresh();
    }

    private function ensureAdministrator(User $operator): void
    {
        if (! $operator->isAdministrator()) {
            throw new AuthorizationException('只有管理员可以管理档案共建者。');
        }
    }
}