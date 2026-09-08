<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    public const RULE_TYPES = [
        'manual' => '手动发放',
        'registered' => '注册用户',
        'favorites_count' => '收藏数量',
        'comments_count' => '评论数量',
        'supporter' => '支持者身份',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon_key',
        'color',
        'rule_type',
        'rule_threshold',
        'is_active',
        'sort_order',
        'internal_note',
    ];

    protected $attributes = [
        'rule_type' => 'manual',
        'color' => '#3da9fc',
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'rule_threshold' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRuleLabelAttribute(): string
    {
        return self::RULE_TYPES[$this->rule_type] ?? '未知规则';
    }
}
