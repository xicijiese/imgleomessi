<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoSimilarityCandidate extends Model
{
    public const STATUSES = [
        'pending_review' => '待核对',
        'kept_separate' => '保留为独立图片',
        'confirmed_duplicate' => '确认重复候选',
        'ignored' => '忽略',
    ];

    protected $fillable = [
        'photo_id',
        'candidate_photo_id',
        'distance',
        'similarity_score',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $attributes = [
        'status' => 'pending_review',
    ];

    protected function casts(): array
    {
        return [
            'photo_id' => 'integer',
            'candidate_photo_id' => 'integer',
            'distance' => 'integer',
            'similarity_score' => 'float',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function candidatePhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'candidate_photo_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', 'pending_review');
    }
}