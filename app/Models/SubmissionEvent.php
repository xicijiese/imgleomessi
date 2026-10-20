<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionEvent extends Model
{
    public const ACTIONS = ['applied' => '申请资格', 'approved' => '通过资格', 'rejected' => '拒绝资格', 'revoked' => '撤销资格', 'submitted' => '提交投稿', 'edited' => '投稿人修改', 'withdrawn' => '撤回投稿', 'reviewer_edited' => '整理资料', 'review_accepted' => '决定采纳', 'review_rejected' => '未采纳', 'replacement_before' => '高清替换前快照', 'imported' => '入库完成', 'retried' => '重试入库', 'cleaned' => '清理暂存', 'contribution_revoked' => '纠正贡献', 'batch_notified' => '批次通知'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
