<?php

namespace App\Services;

use App\Models\PaymentLog;
use App\Models\SponsorshipOrder;
use App\Models\SponsorshipPlan;
use App\Models\User;
use App\Notifications\UserCenterNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RealSponsorshipService
{
    public function __construct(private PaymentSettings $settings, private XunhuGateway $gateway, private SponsorshipEntitlements $entitlements) {}

    public function create(User $user, SponsorshipPlan $plan): SponsorshipOrder
    {
        abort_unless(($user->status ?? 'active') === 'active' && ! $user->isBanned(), 403);
        $credentials = $this->settings->credentials();
        if (! $credentials['enabled'] || blank($credentials['appid']) || blank($credentials['secret'])) {
            throw ValidationException::withMessages(['plan_id' => '微信收款尚未开放，请稍后再试。']);
        }
        abort_unless($plan->is_active && $plan->amount_cents > 0 && $plan->amount_cents <= 99999999
            && ($plan->duration_days === null || ($plan->duration_days >= 1 && $plan->duration_days <= 36500))
            && array_key_exists($plan->badge_level, SponsorshipPlan::BADGE_LEVELS), 422);
        if (! app()->environment('testing') && ! XunhuGateway::publicHttps(route('payments.xunhu.notify'))) {
            throw ValidationException::withMessages(['plan_id' => '收款回调必须使用公网 HTTPS 域名，请先配置站点正式访问地址。']);
        }
        $order = DB::transaction(function () use ($user, $plan, $credentials) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(($user->status ?? 'active') === 'active' && ! $user->isBanned(), 403);
            // 避免连点反复发起同一方案的新收款。
            $pending = SponsorshipOrder::where('user_id', $user->id)->where('sponsorship_plan_id', $plan->id)
                ->where('channel', 'xunhu')->where('status', 'pending')
                ->where(fn ($query) => $query->where('created_at', '>', now()->subMinutes(5))->orWhereNotNull('payment_issue'))->latest()->first();
            if ($pending) {
                return $pending;
            }
            $this->entitlements->captureLegacy($user);

            return SponsorshipOrder::create([
                'order_no' => 'SP'.now()->format('YmdHis').Str::upper(Str::random(12)), 'user_id' => $user->id,
                'sponsorship_plan_id' => $plan->id, 'amount_cents' => $plan->amount_cents, 'channel' => 'xunhu', 'status' => 'pending',
                'plan_snapshot' => $plan->only(['name', 'duration_days', 'badge_level', 'amount_cents']), 'gateway_credentials' => $credentials,
            ]);
        });
        // 一个订单只提交一次下单请求，未知结果通过查询或回调恢复。
        if (DB::table('payment_attempts')->where('sponsorship_order_id', $order->id)->exists()) {
            return $order;
        }
        $attempt = DB::transaction(function () use ($order) {
            SponsorshipOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (DB::table('payment_attempts')->where('sponsorship_order_id', $order->id)->exists()) {
                return null;
            }

            return $this->attempt($order, 'create');
        });
        if ($attempt === null) {
            return $order->refresh();
        }
        try {
            $result = $this->gateway->request($order->gateway_credentials, 'create', [
                'version' => '1.1', 'trade_order_id' => $order->order_no,
                'total_fee' => sprintf('%d.%02d', intdiv($order->amount_cents, 100), $order->amount_cents % 100),
                'title' => '档案库赞助支持', 'notify_url' => route('payments.xunhu.notify'),
                'return_url' => route('support.result', ['order' => $order->order_no]),
                'callback_url' => route('support.result', ['order' => $order->order_no]),
            ]);
            $values = [];
            foreach (['url' => 'payment_url', 'url_qrcode' => 'qr_url'] as $key => $field) {
                if (filled($result[$key] ?? null) && XunhuGateway::publicHttps($result[$key])) {
                    $values[$field] = $result[$key];
                }
            }
            if ($values === []) {
                throw new RuntimeException('渠道未返回可用的安全支付地址。');
            }
            SponsorshipOrder::whereKey($order->id)->update([...$values, 'qr_expires_at' => now()->addMinutes(5), 'payment_issue' => null]);
            $this->finish($attempt, 'confirmed');
        } catch (Throwable) {
            $this->issue($order, $attempt, '下单结果待确认，请勿重复支付；可稍后查询或联系管理员。');
        }

        return $order->refresh();
    }

    public function callback(array $payload): void
    {
        $order = SponsorshipOrder::where('channel', 'xunhu')->where('order_no', is_string($payload['trade_order_id'] ?? null) ? $payload['trade_order_id'] : '')->firstOrFail();
        $credentials = $order->gateway_credentials;
        if (! XunhuGateway::verify($payload, $credentials['secret']) || ($payload['appid'] ?? null) !== $credentials['appid']) {
            throw new RuntimeException('回调验签失败。');
        }
        try {
            $this->apply($order, $payload, 'callback');
        } catch (Throwable $exception) {
            $order->update(['payment_issue' => '已验签通知的订单、金额或交易号核对失败，需人工核查。']);
            PaymentLog::create(['sponsorship_order_id' => $order->id, 'channel' => 'xunhu', 'event_type' => 'callback_rejected', 'status' => 'failed', 'message' => '已验签回调未通过订单关联校验。']);
            throw $exception;
        }
    }

    public function query(SponsorshipOrder $order, ?User $operator = null): SponsorshipOrder
    {
        abort_unless($order->channel === 'xunhu', 422);
        $credentials = $this->credentials($order);
        if (blank($credentials['query_gateway'])) {
            $order->update(['checked_at' => now(), 'payment_issue' => '渠道查询接口尚未核实配置，等待异步通知或管理员核对。']);

            return $order->refresh();
        }
        // 同一订单最多每十秒查询一次，避免轮询和后台调度并发消耗渠道额度。
        $claimed = SponsorshipOrder::whereKey($order->id)->where(fn ($query) => $query->whereNull('checked_at')->orWhere('checked_at', '<=', now()->subSeconds(10)))->update(['checked_at' => now()]);
        if (! $claimed) {
            return $order->refresh();
        }
        $attempt = $this->attempt($order, 'query', $operator);
        try {
            $result = $this->gateway->request($credentials, 'query', ['out_trade_order' => $order->order_no]);
            if (($result['trade_order_id'] ?? $result['out_trade_order'] ?? null) !== $order->order_no || ($result['appid'] ?? null) !== $credentials['appid']) {
                throw new RuntimeException('查询结果缺少或不符合订单关联。');
            }
            // 查询 CD 表示取消，不能当成退款回调 CD。
            if (($result['status'] ?? '') === 'CD') {
                SponsorshipOrder::whereKey($order->id)->where('status', 'pending')->update(['status' => 'closed', 'closed_at' => now()]);
            } elseif (($result['status'] ?? '') === 'OD') {
                $this->apply($order, $result, 'query');
            } elseif (($result['status'] ?? '') !== 'WP') {
                throw new RuntimeException('查询状态或签名协议尚未确认。');
            }
            $this->finish($attempt, 'confirmed');
        } catch (Throwable) {
            $this->issue($order, $attempt, '查询未确认，请核对渠道协议、订单及异步通知。');
        }

        return $order->refresh();
    }

    public function refund(SponsorshipOrder $order, User $operator, string $reason): SponsorshipOrder
    {
        abort_unless($operator->isAdministrator() && ($operator->status ?? 'active') === 'active', 403);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 80 || blank($this->credentials($order)['refund_gateway'])) {
            throw ValidationException::withMessages(['admin_note' => '请填写 1–80 字退款原因，并先核实配置渠道退款接口。']);
        }
        $attempt = DB::transaction(function () use ($order, $operator, $reason) {
            $locked = SponsorshipOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->channel === 'xunhu' && $locked->status === 'paid' && in_array($locked->refund_state, [null, 'failed'], true), 422);
            $locked->update(['refund_state' => 'unknown', 'refund_reason' => $reason, 'refund_requested_by' => $operator->id, 'refund_requested_at' => now()]);

            return $this->attempt($locked, 'refund', $operator);
        });
        try {
            $result = $this->gateway->request($this->credentials($order), 'refund', ['trade_order_id' => $order->order_no, 'reason' => $reason]);
            $result['status'] = $result['refund_status'] ?? '';
            $this->apply($order, $result, 'refund');
            $this->finish($attempt, 'confirmed');
        } catch (Throwable) {
            $this->issue($order, $attempt, '退款结果待确认，禁止重复提交；请核对渠道退款记录。');
        }

        return $order->refresh();
    }

    private function credentials(SponsorshipOrder $order): array
    {
        $saved = $order->gateway_credentials;
        $current = $this->settings->credentials();
        // 旧订单保留原 APPID/密钥；允许后续补齐当时尚未配置的查询/退款地址。
        if ($current['appid'] === $saved['appid']) {
            foreach (['query_gateway', 'refund_gateway'] as $key) {
                $saved[$key] = $saved[$key] ?: $current[$key];
            }
        }

        return $saved;
    }

    private function apply(SponsorshipOrder $order, array $data, string $source): void
    {
        DB::transaction(function () use ($order, $data, $source): void {
            // 所有支付状态变化使用相同锁顺序，跨订单续费不会丢失。
            $user = User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $order = SponsorshipOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $status = $data['status'] ?? '';
            $platformOrder = $data['open_order_id'] ?? null;
            if (is_string($platformOrder) && $platformOrder !== '' && strlen($platformOrder) <= 255) {
                if ($order->platform_order_id && $order->platform_order_id !== $platformOrder) {
                    throw new RuntimeException('渠道订单号不符。');
                }
                $order->platform_order_id = $platformOrder;
            }
            $number = $data['trade_order_id'] ?? $data['out_trade_order'] ?? null;
            if ($number !== $order->order_no || (isset($data['appid']) && $data['appid'] !== $order->gateway_credentials['appid'])) {
                throw new RuntimeException('订单关联不符。');
            }
            $amountKey = $source === 'refund' ? 'refund_fee' : 'total_fee';
            if (XunhuGateway::cents($data[$amountKey] ?? null) !== $order->amount_cents) {
                throw new RuntimeException('金额不符。');
            }
            $transaction = $data['transaction_id'] ?? null;
            if (! is_string($transaction) || $transaction === '' || strlen($transaction) > 255 || ($order->gateway_transaction && $order->gateway_transaction !== $transaction)) {
                throw new RuntimeException('交易号不符。');
            }
            if ($status === 'OD' && $source !== 'refund') {
                if (in_array($order->status, ['paid', 'refunded'])) {
                    return;
                }
                $order->update(['status' => 'paid', 'paid_at' => now(), 'transaction_id' => $transaction, 'gateway_transaction' => $transaction, 'payment_issue' => null]);
                $this->entitlements->rebuild($user);
                if (SponsorshipOrder::where('user_id', $user->id)->where('sponsorship_plan_id', $order->sponsorship_plan_id)->where('channel', 'xunhu')->where('status', 'paid')->whereKeyNot($order->id)->where('paid_at', '>', now()->subMinutes(10))->exists()) {
                    $order->update(['payment_issue' => '短时间内同方案重复收款，均已入账，请核查是否需要退款。']);
                }
                $user->notify(new UserCenterNotification('sponsorship', '赞助支持已确认', '感谢支持本站维护，订单已入账。一次性支持只保留支持记录。', '/me/sponsorships', 'sponsorship_order', $order->id));
            } elseif ($status === 'CD') {
                if ($order->status === 'refunded') {
                    return;
                }
                // 漏收支付通知时，退款通知仍能建立完整收退款记录。
                $order->update(['status' => 'refunded', 'paid_at' => $order->paid_at ?? now(), 'transaction_id' => $transaction, 'gateway_transaction' => $transaction, 'refunded_at' => now(), 'refund_state' => 'completed', 'refund_no' => $data['out_refund_no'] ?? null, 'payment_issue' => null]);
                $this->entitlements->rebuild($user, true);
                $user->notify(new UserCenterNotification('sponsorship', '赞助订单已退款', '整单原路退款已由渠道确认，该订单的支持权益已撤回。', '/me/sponsorships', 'sponsorship_order', $order->id));
            } elseif (in_array($status, ['RD', 'UD']) || ($status === 'OD' && $source === 'refund')) {
                if ($order->status !== 'refunded') {
                    $order->update(['refund_state' => $status === 'RD' ? 'processing' : ($status === 'UD' ? 'failed' : 'unknown')]);
                }
            } else {
                throw new RuntimeException('不支持的支付状态。');
            }
            PaymentLog::create(['sponsorship_order_id' => $order->id, 'channel' => 'xunhu', 'event_type' => $source, 'status' => 'processed', 'payload' => ['status' => $status], 'message' => '渠道验签及订单核对完成。']);
        });
    }

    private function attempt(SponsorshipOrder $order, string $operation, ?User $operator = null): int
    {
        return DB::table('payment_attempts')->insertGetId(['sponsorship_order_id' => $order->id, 'operation' => $operation, 'status' => 'unknown', 'operator_id' => $operator?->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function finish(int $attempt, string $status): void
    {
        DB::table('payment_attempts')->where('id', $attempt)->update(['status' => $status, 'updated_at' => now()]);
    }

    private function issue(SponsorshipOrder $order, int $attempt, string $message): void
    {
        $this->finish($attempt, 'unknown');
        SponsorshipOrder::whereKey($order->id)->update(['payment_issue' => $message]);
        PaymentLog::create(['sponsorship_order_id' => $order->id, 'channel' => 'xunhu', 'event_type' => 'needs_review', 'status' => 'failed', 'message' => $message]);
    }
}
