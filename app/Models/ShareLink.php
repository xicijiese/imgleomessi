<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareLink extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true, 'poster_status' => 'none', 'operations' => 0, 'visits' => 0, 'unique_visitors' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'poster_requested_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals()
    {
        return $this->hasMany(ShareReferral::class);
    }
}
