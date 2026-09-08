<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupporterProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'show_publicly',
        'total_amount_cents',
        'badge_level',
        'last_supported_at',
    ];

    protected $attributes = [
        'show_publicly' => true,
        'total_amount_cents' => 0,
        'badge_level' => 'supporter',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'show_publicly' => 'boolean',
            'total_amount_cents' => 'integer',
            'last_supported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('show_publicly', true);
    }

    public function displayName(): string
    {
        return filled($this->display_name) ? $this->display_name : ($this->user?->name ?? '匿名支持者');
    }

    public function badgeLabel(): string
    {
        return SponsorshipPlan::BADGE_LEVELS[$this->badge_level] ?? $this->badge_level;
    }
}
