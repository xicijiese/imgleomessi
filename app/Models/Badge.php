<?php

namespace App\Models;

use App\Services\PublicMediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Badge extends Model
{
    public const RULE_TYPES = [
        'manual' => '手动发放',
        'registered' => '注册用户',
        'favorites_count' => '收藏数量',
        'comments_count' => '评论数量',
        'supporter' => '运营守护者身份',
        'anniversary' => '纪念日登录访问',
        'contributions_count' => '累计采纳 50 张投稿（待开放）',
        'referrals_count' => '累计 50 位有效推广访客（待开放）',
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
        'image_path',
        'starts_on',
        'ends_on',
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
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Badge $badge): void {
            if (in_array($badge->rule_type, ['contributions_count', 'referrals_count'], true)) {
                $badge->rule_threshold = 50;
            }
        });
        static::deleting(function (Badge $badge): void {
            if ($badge->userBadges()->exists() || BadgeGrantBatch::query()->where('badge_id', $badge->id)->exists()) {
                throw ValidationException::withMessages(['badge' => '已有发放记录的勋章只能停用，不能删除。']);
            }
        });
    }

    public function getImageUrlAttribute(): ?string
    {
        return PublicMediaUrl::fromPublicDisk($this->image_path);
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
