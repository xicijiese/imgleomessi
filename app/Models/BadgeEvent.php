<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeEvent extends Model
{
    public const ACTIONS = ['awarded' => '授予', 'revoked' => '撤销', 'restored' => '恢复'];

    protected $guarded = ['id'];

    public function userBadge(): BelongsTo
    {
        return $this->belongsTo(UserBadge::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
