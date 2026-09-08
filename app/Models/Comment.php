<?php

namespace App\Models;

use App\Notifications\UserCenterNotification;
use App\Services\BadgeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    public const TYPES = [
        'discussion' => '普通评论',
        'correction' => '图片信息补充/纠错',
    ];

    public const STATUSES = [
        'pending' => '待审核',
        'published' => '已发布',
        'rejected' => '已拒绝',
        'hidden' => '已隐藏',
        'deleted' => '已删除',
    ];

    public const CORRECTION_FIELDS = [
        'title' => '标题',
        'description' => '说明',
        'taken_at' => '拍摄时间',
        'event_date' => '事件日期',
        'competition' => '赛事',
        'team' => '球队',
        'people' => '人物同框',
        'source' => '来源',
        'copyright' => '版权备注',
        'category' => '分类',
        'tag' => '标签',
        'other' => '其他',
    ];

    protected $fillable = [
        'user_id',
        'photo_id',
        'parent_id',
        'type',
        'content',
        'status',
        'correction_field',
        'suggested_value',
        'evidence_url',
        'meta',
        'like_count',
        'reviewed_by',
        'reviewed_at',
        'moderation_note',
        'risk_level',
        'sensitive_word_hits',
    ];

    protected $attributes = [
        'type' => 'discussion',
        'status' => 'pending',
        'risk_level' => 'clean',
        'like_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'photo_id' => 'integer',
            'parent_id' => 'integer',
            'meta' => 'array',
            'like_count' => 'integer',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
            'sensitive_word_hits' => 'array',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'target_id')->where('target_type', 'comment');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeDiscussion(Builder $query): Builder
    {
        return $query->where('type', 'discussion');
    }

    public function scopeCorrection(Builder $query): Builder
    {
        return $query->where('type', 'correction');
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function review(string $status, ?User $reviewer = null, ?string $note = null): bool
    {
        if (! array_key_exists($status, self::STATUSES)) {
            return false;
        }

        $meta = $this->meta ?? [];
        $history = $meta['moderation_history'] ?? [];
        $history[] = [
            'status' => $status,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now()->toISOString(),
            'note' => $note,
        ];
        $meta['moderation_history'] = $history;

        $updated = $this->update([
            'status' => $status,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
            'moderation_note' => $note,
            'meta' => $meta,
        ]);

        if ($updated && $this->user !== null) {
            $typeLabel = self::TYPES[$this->type] ?? '内容';
            $statusLabel = self::STATUSES[$status] ?? '已处理';

            $this->user->notify(new UserCenterNotification(
                'comment_review',
                $typeLabel.'审核结果',
                '你的'.$typeLabel.'已更新为：'.$statusLabel.'。',
                '/me/comments',
                'comment',
                $this->id,
            ));

            if ($status === 'published' && $this->type === 'discussion') {
                app(BadgeService::class)->evaluate($this->user);
            }
        }

        return $updated;
    }

    /**
     * @param  array{risk_level: string, hits: array<int, array{word: string, severity: string}>}  $scan
     */
    public function applySensitiveScan(array $scan): bool
    {
        return $this->update([
            'risk_level' => $scan['risk_level'],
            'sensitive_word_hits' => $scan['hits'] === [] ? null : $scan['hits'],
        ]);
    }
}
