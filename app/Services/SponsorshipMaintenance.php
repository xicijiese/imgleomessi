<?php

namespace App\Services;

use App\Jobs\ReconcileSponsorshipOrder;
use App\Models\SponsorshipOrder;
use App\Models\User;
use App\Notifications\UserCenterNotification;
use Illuminate\Support\Facades\DB;

class SponsorshipMaintenance
{
    public function reconcile(): int
    {
        $count = 0;
        // 有界补查最近订单；退款未知结果只等待通知或人工核查，不重发退款。
        SponsorshipOrder::where('channel', 'xunhu')->where('status', 'pending')->where('created_at', '>=', now()->subDay())
            ->where(fn ($query) => $query->whereNull('checked_at')->orWhere('checked_at', '<', now()->subMinutes(5)))
            ->orderBy('checked_at')->orderBy('id')->limit(20)->get()->each(function ($order) use (&$count): void {
                ReconcileSponsorshipOrder::dispatch($order->id)->onConnection(app(QueueWorkerStatus::class)->connection());
                $count++;
            });

        return $count;
    }

    public function remind(): int
    {
        $count = 0;
        // 到期后及时重算展示等级，不撤销非退款场景下永久保留的已获勋章。
        DB::table('sponsorship_accounts')->orderBy('user_id')->chunkById(100, function ($accounts): void {
            foreach ($accounts as $account) {
                DB::transaction(function () use ($account): void {
                    $user = User::whereKey($account->user_id)->lockForUpdate()->first();
                    if ($user) {
                        app(SponsorshipEntitlements::class)->rebuild($user);
                    }
                });
            }
        }, 'user_id');
        foreach ([7, 1] as $days) {
            User::where('status', 'active')->where('supporter_until', '>', now()->addDays($days - 1))->where('supporter_until', '<=', now()->addDays($days))
                ->whereIn('id', DB::table('sponsorship_accounts')->where('legacy_permanent', false)->select('user_id'))->chunkById(100, function ($users) use ($days, &$count): void {
                    foreach ($users as $user) {
                        DB::transaction(function () use ($user, $days, &$count): void {
                            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                            if (! $user->supporter_until?->isFuture() || $user->supporter_until->gt(now()->addDays($days)) || $user->isBanned()) {
                                return;
                            }
                            $inserted = DB::table('sponsorship_reminders')->insertOrIgnore(['user_id' => $user->id, 'expires_at' => $user->supporter_until, 'days' => $days, 'created_at' => now(), 'updated_at' => now()]);
                            if ($inserted) {
                                $user->notify(new UserCenterNotification('sponsorship', '支持身份即将到期', '你的支持身份将于 '.$user->supporter_until->timezone('Asia/Shanghai')->format('Y-m-d H:i').' 到期（北京时间），这是提前 '.$days.' 天的提醒。可自愿续费，历史支持记录继续保留。', '/me/sponsorships'));
                                $count++;
                            }
                        });
                    }
                });
        }

        return $count;
    }
}
