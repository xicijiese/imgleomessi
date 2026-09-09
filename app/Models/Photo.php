<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Photo extends Model
{
    public const STATUSES = [
        'draft' => '草稿',
        'published' => '已发布',
        'archived' => '已归档',
    ];

    public const COPYRIGHT_STATUSES = [
        'unknown' => '待确认',
        'credited' => '已标注来源',
        'restricted' => '受限使用',
        'remove_requested' => '请求下架',
    ];

    public const WATERMARK_STATUSES = [
        'unknown' => '未知',
        'none' => '无水印',
        'present' => '有水印',
    ];

    protected $attributes = [
        'status' => 'draft',
        'copyright_status' => 'unknown',
        'watermark_status' => 'unknown',
        'publish_after_processing' => false,
    ];

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'original_filename',
        'stored_filename',
        'taken_at',
        'event_date',
        'source_id',
        'copyright_status',
        'watermark_status',
        'status',
        'publish_after_processing',
        'width',
        'height',
        'mime_type',
        'file_size',
        'original_key',
        'display_key',
        'thumbnail_key',
        'uploaded_by',
        'photo_upload_batch_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'event_date' => 'date',
            'source_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
            'uploaded_by' => 'integer',
            'photo_upload_batch_id' => 'integer',
            'published_at' => 'datetime',
            'publish_after_processing' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Photo $photo): void {
            if (blank($photo->uuid)) {
                $photo->uuid = (string) Str::uuid();
            }
        });
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function uploadBatch(): BelongsTo
    {
        return $this->belongsTo(PhotoUploadBatch::class, 'photo_upload_batch_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'photo_category');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'photo_tag');
    }

        public function opponents(): BelongsToMany
    {
        return $this->belongsToMany(Opponent::class, 'opponent_photo');
    }
public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'album_photo');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(PhotoFavorite::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PhotoLike::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(PhotoShare::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function analysisResult(): HasOne
    {
        return $this->hasOne(PhotoAnalysisResult::class);
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(ProcessingJob::class);
    }

    public function similarityCandidates(): HasMany
    {
        return $this->hasMany(PhotoSimilarityCandidate::class);
    }

    public function similarityMatches(): HasMany
    {
        return $this->hasMany(PhotoSimilarityCandidate::class, 'candidate_photo_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function hasCompleteCategorySet(): bool
    {
        $categories = $this->categories()->children()->get(['categories.id', 'categories.parent_id']);
        $requiredRootIds = Category::requiredRootIds();

        if ($requiredRootIds === []) {
            return false;
        }

        $selectedByParent = $categories->groupBy('parent_id');

        foreach ($requiredRootIds as $rootId) {
            if ($selectedByParent->get($rootId, collect())->count() !== 1) {
                return false;
            }
        }

        return true;
    }

    public function canBePublished(): bool
    {
        if (! filled($this->title)) {
            return false;
        }

        if ($this->status === 'archived') {
            return false;
        }

        if (in_array($this->copyright_status, ['restricted', 'remove_requested'], true)) {
            return false;
        }

        if (blank($this->display_key) || blank($this->thumbnail_key)) {
            return false;
        }

        return $this->hasCompleteCategorySet();
    }

    public function publish(): bool
    {
        if (! $this->canBePublished()) {
            return false;
        }

        return $this->update([
            'status' => 'published',
            'publish_after_processing' => false,
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    public function archive(): bool
    {
        return $this->update([
            'status' => 'archived',
            'publish_after_processing' => false,
        ]);
    }

    public function restoreFromArchive(): bool
    {
        if ($this->status !== 'archived') {
            return false;
        }

        return $this->update([
            'status' => 'draft',
            'publish_after_processing' => false,
            'published_at' => null,
        ]);
    }
}
