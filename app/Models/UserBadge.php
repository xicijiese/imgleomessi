<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBadge extends Model
{
    public const SOURCES = [
        'rule' => '规则获得',
        'manual' => '后台发放',
    ];

    public const STATUSES = [
        'earned' => '已获得',
        'revoked' => '已撤销',
    ];

    protected $fillable = [
        'user_id',
        'badge_id',
        'source',
        'status',
        'equipped',
        'awarded_by',
        'awarded_at',
        'revoked_at',
        'note',
    ];

    protected $attributes = [
        'source' => 'rule',
        'status' => 'earned',
        'equipped' => false,
    ];

    protected function casts(): array
    {
        return [
            'equipped' => 'boolean',
            'awarded_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->source] ?? '未知来源';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? '未知状态';
    }
}
