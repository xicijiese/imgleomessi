<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PhotoSubmission extends Model
{
    public const STATUSES = ['uploading' => '上传中', 'temporary' => '待提交', 'pending' => '待审核', 'processing' => '入库处理中', 'failed' => '入库失败', 'accepted' => '已采纳', 'rejected' => '未采纳', 'withdrawn' => '已撤回'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['import_location' => 'array', 'similar_photo_ids' => 'array', 'metadata' => 'array', 'storage_location' => 'array', 'show_credit' => 'boolean', 'revision' => 'integer',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'accepted_at' => 'datetime',
            'contribution_revoked_at' => 'datetime', 'cleanup_at' => 'datetime', 'cleaned_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (PhotoSubmission $submission): void {
            // 文件资产标识由服务端生成，不使用客户端请求标识，避免覆盖已有图库路径。
            $submission->asset_uuid = (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
