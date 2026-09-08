<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\UserCenterNotification;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    public function evaluate(User $user): void
    {
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
            $userBadge = UserBadge::query()->firstOrNew([
                'user_id' => $user->id,
                'badge_id' => $badge->id,
            ]);
            $wasEarned = $userBadge->exists && $userBadge->status === 'earned';

            if (! $wasEarned) {
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

                $user->notify(new UserCenterNotification(
                    'badge',
                    '获得新勋章',
                    '你获得了「'.$badge->name.'」勋章。',
                    '/me/badges',
                    'badge',
                    $badge->id,
                ));
            }

            if (! $user->userBadges()->where('status', 'earned')->where('equipped', true)->exists()) {
                $this->equip($user, $badge);
                $userBadge->refresh();
            }

            return $userBadge;
        });
    }

    public function revoke(UserBadge $userBadge, ?User $operator = null, ?string $note = null): UserBadge
    {
        $userBadge->update([
            'status' => 'revoked',
            'equipped' => false,
            'awarded_by' => $operator?->id ?? $userBadge->awarded_by,
            'revoked_at' => now(),
            'note' => $note ?? $userBadge->note,
        ]);

        return $userBadge->refresh();
    }

    public function equip(User $user, Badge $badge): UserBadge
    {
        return DB::transaction(function () use ($user, $badge): UserBadge {
            $userBadge = $user->userBadges()
                ->where('badge_id', $badge->id)
                ->where('status', 'earned')
                ->firstOrFail();

            $user->userBadges()->where('equipped', true)->update(['equipped' => false]);
            $userBadge->update(['equipped' => true]);

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
            ->active()
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
            'badges' => $badges,
        ];
    }

    private function qualifies(User $user, Badge $badge): bool
    {
        return match ($badge->rule_type) {
            'registered' => true,
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
            default => 0,
        };

        if ($badge->rule_type === 'registered') {
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
