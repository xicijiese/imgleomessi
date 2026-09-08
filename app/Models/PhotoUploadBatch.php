<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
class PhotoUploadBatch extends Model
{
    public const MODES = [
        'standalone' => '单独上传',
        'album' => '相册内上传',
    ];

    public const STATUSES = [
        'draft' => '草稿',
        'processing' => '处理中',
        'completed' => '已完成',
        'failed' => '失败',
        'partially_failed' => '部分失败',
    ];

    protected $attributes = [
        'status' => 'draft',
        'total_count' => 0,
        'success_count' => 0,
        'failed_count' => 0,
    ];

    protected $fillable = [
        'mode',
        'album_id',
        'uploaded_by',
        'status',
        'total_count',
        'success_count',
        'failed_count',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'album_id' => 'integer',
            'uploaded_by' => 'integer',
            'total_count' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }


    public function processingJobs(): HasManyThrough
    {
        return $this->hasManyThrough(ProcessingJob::class, Photo::class, 'photo_upload_batch_id', 'photo_id');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
}
