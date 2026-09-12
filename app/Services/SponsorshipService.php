<?php

namespace App\Services;

use App\Models\PaymentLog;
use App\Models\SponsorshipOrder;
use App\Models\SponsorshipPlan;
use App\Models\SupporterProfile;
use App\Models\User;
use App\Notifications\UserCenterNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SponsorshipService
{
    public function createOrder(User $user, SponsorshipPlan $plan, string $channel = 'mock'): SponsorshipOrder
    {
        abort_unless($plan->is_active, 404);

        return DB::transaction(function () use ($user, $plan, $channel): SponsorshipOrder {
            $order = SponsorshipOrder::query()->create([
                'order_no' => $this->orderNo(),
                'user_id' => $user->id,
                'sponsorship_plan_id' => $plan->id,
                'amount_cents' => $plan->amount_cents,
                'channel' => array_key_exists($channel, SponsorshipOrder::CHANNELS) ? $channel : 'mock',
                'status' => 'pending',
            ]);

            $this->log($order, 'order_created', 'received', [
                'plan_id' => $plan->id,
                'amount_cents' => $plan->amount_cents,
                'channel' => $order->channel,
            ], '赞助订单已创建。');

            return $order;
        });
    }

    public function markPaid(SponsorshipOrder $order, string $channel = 'mock', ?User $operator = null, ?string $note = null): SponsorshipOrder
    {
        if (! $order->canBePaid()) {
            return $order;
        }

        return DB::transaction(function () use ($order, $channel, $operator, $note): SponsorshipOrder {
            $order->loadMissing(['plan', 'user']);
            $paidAt = now();

            $order->update([
                'status' => 'paid',
                'channel' => array_key_exists($channel, SponsorshipOrder::CHANNELS) ? $channel : $order->channel,
                'transaction_id' => $order->transaction_id ?? strtoupper($channel).'-'.Str::upper(Str::random(12)),
                'paid_at' => $paidAt,
                'raw_callback_json' => [
                    'channel' => $channel,
                    'handled_by' => $operator?->id,
                    'handled_at' => $paidAt->toISOString(),
                ],
                'admin_note' => $note ?? $order->admin_note,
            ]);

            $this->refreshSupporter($order);
            $this->log($order, 'payment_success', 'processed', $order->raw_callback_json, '赞助订单已标记为已支付。');
            $order->user->notify(new UserCenterNotification(
                'sponsorship',
                '赞助支持已确认',
                '感谢你支持本站维护，运营守护者身份已更新。',
                '/me/sponsorships',
                'sponsorship_order',
                $order->id,
            ));
            app(BadgeService::class)->evaluate($order->user);

            return $order->refresh();
        });
    }

    public function close(SponsorshipOrder $order, ?User $operator = null, ?string $note = null): SponsorshipOrder
    {
        if ($order->status !== 'pending') {
            return $order;
        }

        $order->update([
            'status' => 'closed',
            'closed_at' => now(),
            'admin_note' => $note ?? $order->admin_note,
        ]);

        $this->log($order, 'order_closed', 'processed', ['handled_by' => $operator?->id], '赞助订单已关闭。');

        return $order->refresh();
    }

    public function refund(SponsorshipOrder $order, ?User $operator = null, ?string $note = null): SponsorshipOrder
    {
        if ($order->status !== 'paid') {
            return $order;
        }

        $order->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'admin_note' => $note ?? $order->admin_note,
        ]);

        $this->log($order, 'order_refunded', 'processed', ['handled_by' => $operator?->id], '赞助订单已标记为退款。');

        return $order->refresh();
    }

    private function refreshSupporter(SponsorshipOrder $order): void
    {
        $order->loadMissing(['plan', 'user.supporterProfile']);
        $plan = $order->plan;

        if (! $plan instanceof SponsorshipPlan) {
            throw new RuntimeException('赞助订单缺少方案，无法更新运营守护者身份。');
        }

        $currentUntil = $order->user->supporter_until;
        $baseUntil = $currentUntil?->isFuture() ? $currentUntil : now();
        $supporterUntil = $plan->duration_days !== null
            ? $baseUntil->copy()->addDays($plan->duration_days)
            : $currentUntil;

        $order->user->update([
            'supporter_until' => $supporterUntil,
        ]);

        SupporterProfile::query()->updateOrCreate(
            ['user_id' => $order->user_id],
            [
                'display_name' => $order->user->supporterProfile?->display_name ?? $order->user->name,
                'show_publicly' => $order->user->supporterProfile?->show_publicly ?? true,
                'total_amount_cents' => $order->user->sponsorshipOrders()->paid()->sum('amount_cents'),
                'badge_level' => $plan->badge_level,
                'last_supported_at' => $order->paid_at ?? now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function log(SponsorshipOrder $order, string $eventType, string $status, ?array $payload, ?string $message = null): PaymentLog
    {
        return PaymentLog::query()->create([
            'sponsorship_order_id' => $order->id,
            'channel' => $order->channel,
            'event_type' => $eventType,
            'status' => $status,
            'payload' => $payload,
            'message' => $message,
        ]);
    }

    private function orderNo(): string
    {
        do {
            $orderNo = 'SP'.now()->format('YmdHis').Str::upper(Str::random(6));
        } while (SponsorshipOrder::query()->where('order_no', $orderNo)->exists());

        return $orderNo;
    }
}
