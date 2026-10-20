<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\BadgeEvent;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\UserCenterNotification;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    public function evaluate(User $user): void
    {
        if (($user->status ?? 'active') !== 'active' || $user->isBanned()) {
            return;
        }

        Badge::query()
            ->active()
            ->where('rule_type', '!=', 'manual')
            ->orderBy('sort_order')
            ->get()
            ->each(function (Badge $badge) use ($user): void {
                if ($this->qualifies($user, $badge)) {
                    $this->award($user, $badge, 'rule');
                }
            });
    }

    public function award(User $user, Badge $badge, string $source = 'rule', ?User $operator = null, ?string $note = null): UserBadge
    {
        return DB::transaction(function () use ($user, $badge, $source, $operator, $note): UserBadge {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $badge = Badge::query()->findOrFail($badge->id);
            $userBadge = UserBadge::query()->firstOrNew([
                'user_id' => $user->id,
                'badge_id' => $badge->id,
            ]);

            if ($userBadge->exists || ! $badge->is_active || ($user->status ?? 'active') !== 'active' || $user->isBanned()) {
                return $userBadge;
            }

            $userBadge->fill([
                'source' => array_key_exists($source, UserBadge::SOURCES) ? $source : 'rule',
                'status' => 'earned',
                'equipped' => false,
                'awarded_by' => $operator?->id,
                'awarded_at' => now(),
                'revoked_at' => null,
                'note' => $note,
            ]);
            $userBadge->save();
            $this->record($userBadge, 'awarded', $operator, $note);

            $user->notify(new UserCenterNotification(
                'badge',
                '获得新勋章',
                '你获得了「'.$badge->name.'」勋章。',
                '/me/badges',
                'badge',
                $badge->id,
            ));

            return $userBadge;
        });
    }

    public function revoke(UserBadge $userBadge, ?User $operator = null, ?string $note = null): UserBadge
    {
        return DB::transaction(function () use ($userBadge, $operator, $note): UserBadge {
            $userBadge = UserBadge::query()->lockForUpdate()->findOrFail($userBadge->id);
            if ($userBadge->status === 'earned') {
                $userBadge->update(['status' => 'revoked', 'equipped' => false, 'revoked_at' => now()]);
                $this->record($userBadge, 'revoked', $operator, $note);
            }

            return $userBadge;
        });
    }

    public function restore(UserBadge $userBadge, User $operator, string $note): UserBadge
    {
        abort_unless($operator->isAdministrator(), 403);

        return DB::transaction(function () use ($userBadge, $operator, $note): UserBadge {
            $userBadge = UserBadge::query()->lockForUpdate()->findOrFail($userBadge->id);
            if ($userBadge->status === 'revoked') {
                $userBadge->update(['status' => 'earned', 'equipped' => false, 'revoked_at' => null]);
                $this->record($userBadge, 'restored', $operator, $note);
                $userBadge->user->notify(new UserCenterNotification('badge', '勋章已恢复', '「'.$userBadge->badge->name.'」已恢复，可自行选择佩戴。', '/me/badges', 'badge', $userBadge->badge_id));
            }

            return $userBadge;
        });
    }

    private function record(UserBadge $userBadge, string $action, ?User $operator, ?string $note): void
    {
        BadgeEvent::query()->create(['user_badge_id' => $userBadge->id, 'operator_id' => $operator?->id, 'action' => $action, 'note' => $note]);
    }

    public function equip(User $user, Badge $badge, bool $equipped = true): UserBadge
    {
        return DB::transaction(function () use ($user, $badge, $equipped): UserBadge {
            $userBadge = $user->userBadges()
                ->where('badge_id', $badge->id)
                ->where('status', 'earned')
                ->lockForUpdate()
                ->firstOrFail();

            $userBadge->update(['equipped' => $equipped]);

            return $userBadge->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $shell
     * @return array<string, mixed>
     */
    public function userCenterPage(User $user, array $shell): array
    {
        $this->evaluate($user);
        $earned = $user->userBadges()
            ->where('status', 'earned')
            ->with('badge')
            ->get()
            ->keyBy('badge_id');

        $badges = Badge::query()
            ->where(fn ($query) => $query->active()->orWhereIn('id', $earned->keys()))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Badge $badge) use ($user, $earned): array {
                $userBadge = $earned->get($badge->id);
                $progress = $this->progress($user, $badge);

                return [
                    'id' => $badge->id,
                    'name' => $badge->name,
                    'slug' => $badge->slug,
                    'description' => $badge->description,
                    'icon_key' => $badge->icon_key,
                    'color' => $badge->color,
                    'image_url' => $badge->image_url,
                    'is_active' => $badge->is_active,
                    'availability' => $this->availability($badge),
                    'rule_type' => $badge->rule_type,
                    'rule_label' => $badge->rule_label,
                    'rule_threshold' => $badge->rule_threshold,
                    'is_earned' => $userBadge !== null,
                    'is_equipped' => $userBadge?->equipped === true,
                    'earned_at' => $userBadge?->awarded_at?->format('Y-m-d H:i'),
                    'progress_current' => $progress['current'],
                    'progress_target' => $progress['target'],
                    'progress_percent' => $progress['percent'],
                ];
            })
            ->all();

        $equipped = collect($badges)->first(fn (array $badge): bool => $badge['is_equipped']);

        return [
            ...$shell,
            'equipped_badge' => $equipped,
            'equipped_badges' => collect($badges)->where('is_equipped', true)->values()->all(),
            'badges' => $badges,
        ];
    }

    public function anniversaryOpen(Badge $badge): bool
    {
        $today = now('Asia/Shanghai')->toDateString();

        return $badge->starts_on !== null && $badge->ends_on !== null
            && $today >= $badge->starts_on->toDateString() && $today <= $badge->ends_on->toDateString();
    }

    public function evaluateAnniversaries(User $user): void
    {
        if (($user->status ?? 'active') !== 'active' || $user->isBanned()) {
            return;
        }
        Badge::query()->active()->where('rule_type', 'anniversary')->get()->each(function (Badge $badge) use ($user): void {
            if ($this->anniversaryOpen($badge)) {
                $this->award($user, $badge);
            }
        });
    }

    public function availability(Badge $badge): string
    {
        if (! $badge->is_active) {
            return '已停用，已获勋章永久保留';
        }
        if (in_array($badge->rule_type, ['contributions_count', 'referrals_count'], true)) {
            return '对应功能开放后可获得，累计目标为 50';
        }
        if ($badge->rule_type === 'anniversary') {
            return $badge->starts_on && $badge->ends_on
                ? $badge->starts_on->format('Y-m-d').' 至 '.$badge->ends_on->format('Y-m-d').'（北京时间）登录访问自动获得'
                : '纪念日期尚未设置';
        }

        return $badge->rule_type === 'manual' ? '由管理员发放' : '达成条件后自动获得';
    }

    private function qualifies(User $user, Badge $badge): bool
    {
        return match ($badge->rule_type) {
            'registered' => true,
            'anniversary' => $this->anniversaryOpen($badge),
            'favorites_count' => $user->photoFavorites()->count() >= max(1, (int) $badge->rule_threshold),
            'comments_count' => $user->comments()->discussion()->published()->count() >= max(1, (int) $badge->rule_threshold),
            'supporter' => $user->isSupporter(),
            default => false,
        };
    }

    /**
     * @return array{current: int, target: int|null, percent: int}
     */
    private function progress(User $user, Badge $badge): array
    {
        $target = $badge->rule_threshold;
        $current = match ($badge->rule_type) {
            'registered' => 1,
            'favorites_count' => $user->photoFavorites()->count(),
            'comments_count' => $user->comments()->discussion()->published()->count(),
            'supporter' => $user->isSupporter() ? 1 : 0,
            'anniversary' => $this->anniversaryOpen($badge) ? 1 : 0,
            default => 0,
        };

        if (in_array($badge->rule_type, ['registered', 'anniversary'], true)) {
            $target = 1;
        }

        if ($badge->rule_type === 'supporter') {
            $target = 1;
        }

        $percent = $target !== null && $target > 0
            ? min(100, (int) floor(($current / $target) * 100))
            : 0;

        return [
            'current' => $current,
            'target' => $target,
            'percent' => $percent,
        ];
    }
}
