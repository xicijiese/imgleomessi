<?php

namespace App\Models;

use App\Notifications\UserCenterNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public const TARGET_TYPES = [
        'comment' => '评论',
    ];

    public const REASONS = [
        'spam' => '垃圾广告',
        'abuse' => '攻击辱骂',
        'copyright' => '版权或来源问题',
        'misleading' => '误导或不实信息',
        'other' => '其他',
    ];

    public const STATUSES = [
        'pending' => '待处理',
        'resolved' => '已处理',
        'rejected' => '已驳回',
        'closed' => '已关闭',
    ];

    protected $fillable = [
        'user_id',
        'target_type',
        'target_id',
        'reason',
        'details',
        'status',
        'handled_by',
        'handled_at',
        'internal_note',
        'risk_level',
        'sensitive_word_hits',
    ];

    protected $attributes = [
        'target_type' => 'comment',
        'status' => 'pending',
        'risk_level' => 'clean',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'target_id' => 'integer',
            'handled_by' => 'integer',
            'handled_at' => 'datetime',
            'sensitive_word_hits' => 'array',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'target_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeCommentTargets(Builder $query): Builder
    {
        return $query->where('target_type', 'comment');
    }

    public function resolve(?User $handler = null, ?string $note = null, bool $hideTarget = false): bool
    {
        if ($hideTarget && $this->target_type === 'comment') {
            $this->comment?->review('hidden', $handler, $note);
        }

        $updated = $this->update([
            'status' => 'resolved',
            'handled_by' => $handler?->id,
            'handled_at' => now(),
            'internal_note' => $note,
        ]);

        if ($updated && $this->reporter !== null) {
            $this->reporter->notify(new UserCenterNotification(
                'report_result',
                '举报处理结果',
                '你的举报已处理，感谢帮助维护社区秩序。',
                '/me/reports',
                'report',
                $this->id,
            ));
        }

        return $updated;
    }

    public function reject(?User $handler = null, ?string $note = null): bool
    {
        $updated = $this->update([
            'status' => 'rejected',
            'handled_by' => $handler?->id,
            'handled_at' => now(),
            'internal_note' => $note,
        ]);

        if ($updated && $this->reporter !== null) {
            $this->reporter->notify(new UserCenterNotification(
                'report_result',
                '举报处理结果',
                '你的举报已驳回，处理结果已记录。',
                '/me/reports',
                'report',
                $this->id,
            ));
        }

        return $updated;
    }
}
