<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SponsorshipPlan extends Model
{
    public const BADGE_LEVELS = [
        'supporter' => '守护者',
        'silver' => '银色守护者',
        'gold' => '金色守护者',
        'legend' => '传奇守护者',
    ];

    protected $fillable = [
        'name',
        'slug',
        'amount_cents',
        'duration_days',
        'badge_level',
        'benefits',
        'is_active',
        'sort_order',
        'internal_note',
    ];

    protected $attributes = [
        'badge_level' => 'supporter',
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SponsorshipOrder::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function amountLabel(): string
    {
        return '¥'.number_format($this->amount_cents / 100, 2);
    }

    public function badgeLabel(): string
    {
        return self::BADGE_LEVELS[$this->badge_level] ?? $this->badge_level;
    }
}
