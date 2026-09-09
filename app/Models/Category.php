<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const ROOT_CATEGORIES = [
        'career-stage' => '生涯阶段',
        'competition' => '赛事',
        'season' => '赛季',
        'year' => '年份',
        'scene' => '场景',
        'image-type' => '图片类型',
        'person-relation' => '人物关系',
        'source-platform' => '来源平台',
    ];

    /**
     * 发布图片时必须填写的主分类。其他主分类保留为可选资料维度。
     */
    public const REQUIRED_ROOT_SLUGS = [
        'career-stage',
        'year',
        'scene',
    ];

    public const VISIBILITIES = [
        'public' => '公开',
        'hidden' => '隐藏',
    ];

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'visibility',
        'is_system',
        'required_for_publish',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'required_for_publish' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'album_category');
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'photo_category');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeChildren(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeRequiredForPublish(Builder $query): Builder
    {
        return $query
            ->roots()
            ->where('required_for_publish', true)
            ->where('visibility', 'public');
    }

    /**
     * @return array<int, int>
     */
    public static function requiredRootIds(): array
    {
        return static::query()
            ->requiredForPublish()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}
