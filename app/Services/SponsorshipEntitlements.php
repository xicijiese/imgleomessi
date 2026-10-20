<?php

namespace App\Services;

use App\Models\SponsorshipOrder;
use App\Models\SponsorshipPlan;
use App\Models\SupporterProfile;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SponsorshipEntitlements
{
    public function captureLegacy(User $user): void
    {
        $profile = $user->supporterProfile()->first();
        DB::table('sponsorship_accounts')->insertOrIgnore([
            'user_id' => $user->id, 'legacy_until' => $user->supporter_until,
            'legacy_permanent' => $profile !== null && $user->supporter_until === null,
            'legacy_profile' => json_encode([...($profile?->only(['total_amount_cents', 'badge_level', 'last_supported_at']) ?? []), 'badge_ids' => $user->userBadges()->where('status', 'earned')->pluck('id')->all()]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // 从未退款订单快照重算，避免退款简单减天数误伤其他订单。
    public function rebuild(User $user, bool $refund = false): void
    {
        $account = DB::table('sponsorship_accounts')->where('user_id', $user->id)->first();
        $legacy = json_decode($account->legacy_profile ?? 'null', true) ?? [];
        $until = $account->legacy_until ? Carbon::parse($account->legacy_until) : null;
        $ranks = array_flip(array_keys(SponsorshipPlan::BADGE_LEVELS));
        $level = $legacy['badge_level'] ?? 'supporter';
        $activeLevels = [];
        if ($account->legacy_permanent || $until?->isFuture()) {
            $activeLevels[] = $level;
        }
        $orders = SponsorshipOrder::where('user_id', $user->id)->where('channel', 'xunhu')->where('status', 'paid')->orderBy('paid_at')->orderBy('id')->get();
        foreach ($orders as $order) {
            $snapshot = $order->plan_snapshot;
            if ($snapshot['duration_days'] !== null) {
                $base = $until && $until->gt($order->paid_at) ? $until->copy() : $order->paid_at->copy();
                $until = $base->addDays($snapshot['duration_days']);
                if ($until->isFuture()) {
                    $activeLevels[] = $snapshot['badge_level'];
                }
            }
        }
        foreach ($activeLevels as $candidate) {
            if (($ranks[$candidate] ?? 0) >= ($ranks[$level] ?? 0) || ! in_array($level, $activeLevels)) {
                $level = $candidate;
            }
        }
        $user->update(['supporter_until' => $until]);
        $profile = $user->supporterProfile()->first();
        SupporterProfile::updateOrCreate(['user_id' => $user->id], [
            'display_name' => $profile?->display_name ?? $user->name,
            'show_publicly' => $profile?->show_publicly ?? true,
            'total_amount_cents' => ($legacy['total_amount_cents'] ?? 0) + $orders->sum('amount_cents'),
            'badge_level' => $activeLevels === [] ? 'supporter' : $level,
            'last_supported_at' => $orders->last()?->paid_at ?? ($legacy['last_supported_at'] ?? null),
        ]);
        $user->unsetRelation('supporterProfile');
        if ($refund && ! $user->isSupporter()) {
            $user->userBadges()->whereNotIn('id', $legacy['badge_ids'] ?? [])->where('source', 'rule')->where('status', 'earned')
                ->whereHas('badge', fn ($query) => $query->where('rule_type', 'supporter'))
                ->get()->each(fn (UserBadge $badge) => app(BadgeService::class)->revoke($badge, null, '赞助整单退款，已无其他支持身份依据。'));
        }
        app(BadgeService::class)->evaluate($user);
    }
}
