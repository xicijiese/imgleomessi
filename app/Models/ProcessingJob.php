<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingJob extends Model
{
    public const TYPES = [
        'metadata' => '基础信息读取',
        'hash' => '文件哈希计算',
        'ocr_placeholder' => 'OCR 预留',
        'datawanxiang_derivatives' => '生成展示图 / 缩略图',
        'similarity' => '生成相似候选',
        'ocr' => '数据万象 OCR 识别',
    ];

    public const STATUSES = [
        'pending' => '待处理',
        'running' => '处理中',
        'done' => '已完成',
        'failed' => '失败',
    ];

    protected $fillable = [
        'type',
        'photo_id',
        'status',
        'attempts',
        'payload',
        'error_message',
        'processed_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'photo_id' => 'integer',
            'attempts' => 'integer',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? '未知任务';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? '未知状态';
    }
}
