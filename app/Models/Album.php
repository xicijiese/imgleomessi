<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Album extends Model
{
    public const STATUSES = [
        'draft' => '草稿',
        'published' => '已发布',
        'hidden' => '隐藏',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_photo_id',
        'sort_order',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'cover_photo_id' => 'integer',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'album_category');
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'album_photo');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function hasCompleteCategorySet(): bool
    {
        $categories = $this->categories()->children()->get(['categories.id', 'categories.parent_id']);
        $rootCount = Category::query()->roots()->count();

        if ($rootCount === 0 || $categories->count() !== $rootCount) {
            return false;
        }

        return $categories->pluck('parent_id')->unique()->count() === $rootCount;
    }
}
