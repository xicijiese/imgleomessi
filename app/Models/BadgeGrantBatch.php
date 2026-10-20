<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadgeGrantBatch extends Model
{
    public const STATUSES = ['pending' => '排队中', 'running' => '处理中', 'completed' => '已完成', 'failed' => '失败'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BadgeGrantRecipient::class, 'batch_id');
    }
}
