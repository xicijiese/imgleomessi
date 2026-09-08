<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLog extends Model
{
    public const STATUSES = [
        'received' => '已接收',
        'processed' => '已处理',
        'failed' => '处理失败',
    ];

    protected $fillable = [
        'sponsorship_order_id',
        'channel',
        'event_type',
        'status',
        'payload',
        'message',
    ];

    protected $attributes = [
        'channel' => 'mock',
        'status' => 'received',
    ];

    protected function casts(): array
    {
        return [
            'sponsorship_order_id' => 'integer',
            'payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SponsorshipOrder::class, 'sponsorship_order_id');
    }
}
