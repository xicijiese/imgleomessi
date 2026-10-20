<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SponsorshipOrder extends Model
{
    public const CHANNELS = [
        'xunhu' => '微信支付（虎皮椒）',
        'mock' => '模拟支付',
        'manual' => '后台手动',
    ];

    public const REFUND_STATES = ['unknown' => '待确认', 'processing' => '渠道处理中', 'completed' => '已退款', 'failed' => '退款失败'];

    public const STATUSES = [
        'pending' => '待支付',
        'paid' => '已支付',
        'failed' => '支付失败',
        'closed' => '已关闭',
        'refunded' => '已退款',
    ];

    protected $hidden = ['gateway_credentials', 'raw_callback_json'];

    protected $fillable = [
        'plan_snapshot', 'gateway_credentials', 'gateway_transaction', 'platform_order_id',
        'payment_url', 'qr_url', 'qr_expires_at', 'refund_state', 'refund_no', 'refund_reason',
        'refund_requested_by', 'refund_requested_at', 'checked_at', 'payment_issue',
        'order_no',
        'user_id',
        'sponsorship_plan_id',
        'amount_cents',
        'channel',
        'status',
        'transaction_id',
        'paid_at',
        'closed_at',
        'refunded_at',
        'raw_callback_json',
        'admin_note',
    ];

    protected $attributes = [
        'channel' => 'mock',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'plan_snapshot' => 'array',
            'gateway_credentials' => 'encrypted:array',
            'qr_expires_at' => 'datetime',
            'refund_requested_at' => 'datetime',
            'checked_at' => 'datetime',
            'user_id' => 'integer',
            'sponsorship_plan_id' => 'integer',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'raw_callback_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SponsorshipPlan::class, 'sponsorship_plan_id');
    }

    public function paymentLogs(): HasMany
    {
        return $this->hasMany(PaymentLog::class);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function amountLabel(): string
    {
        return '¥'.number_format($this->amount_cents / 100, 2);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel] ?? $this->channel;
    }

    public function canBePaid(): bool
    {
        return $this->status === 'pending';
    }
}
