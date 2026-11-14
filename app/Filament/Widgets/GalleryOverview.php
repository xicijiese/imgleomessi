<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Albums\AlbumResource;
use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\Photos\PhotoResource;
use App\Filament\Resources\PhotoSubmissions\PhotoSubmissionResource;
use App\Filament\Resources\ProcessingJobs\ProcessingJobResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\SubmissionApplications\SubmissionApplicationResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AdminAuditLog;
use App\Models\Album;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\PhotoSubmission;
use App\Models\PhotoUploadBatch;
use App\Models\ProcessingJob;
use App\Models\Report;
use App\Models\SubmissionApplication;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class GalleryOverview extends Widget
{
    protected string $view = 'filament.widgets.gallery-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->canManageGalleryContent() ?? false;
    }

    protected function getViewData(): array
    {
        abort_unless(static::canView(), 403);
        $admin = auth()->user()->isAdministrator();

        // 管理员与编辑员分别缓存，避免敏感统计进入编辑员响应。
        $snapshot = Cache::remember('admin.dashboard.v1.'.($admin ? 'admin' : 'editor'), 60, function () use ($admin): array {
            $snapshot = [
                'updated_at' => now()->timezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
                'photos' => Photo::count(),
                'published' => Photo::where('status', 'published')->count(),
                'albums' => Album::count(),
                'submissions' => PhotoSubmission::where('status', 'pending')->count(),
            ];

            if ($admin) {
                $snapshot += [
                    'users' => User::count(),
                    'corrections' => Comment::correction()->pendingReview()->count(),
                    'reports' => Report::pending()->count(),
                    'applications' => SubmissionApplication::where('status', 'pending')->count(),
                    'processing' => ProcessingJob::where('type', '!=', 'labels')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
                    'batches' => PhotoUploadBatch::with('album:id,title')->latest()->orderByDesc('id')->limit(5)->get()->map(fn ($batch): array => [
                        'id' => $batch->id,
                        'title' => $batch->album?->title ?: '单独上传',
                        'status' => PhotoUploadBatch::STATUSES[$batch->status] ?? $batch->status,
                        'success' => $batch->success_count,
                        'total' => $batch->total_count,
                        'time' => $batch->created_at->timezone('Asia/Shanghai')->format('m-d H:i'),
                    ])->all(),
                    'audits' => AdminAuditLog::with('actor:id,name')->latest('created_at')->orderByDesc('id')->limit(5)->get()->map(fn ($log): array => [
                        'actor' => $log->actor?->name ?: '系统',
                        'action' => match ($log->action) {
                            'user.profile_updated' => '更新用户资料',
                            'user.role_changed' => '调整用户角色',
                            'user.enabled' => '启用用户',
                            'user.disabled' => '停用用户',
                            'user.banned' => '封禁用户',
                            'user.unbanned' => '解除用户封禁',
                            'user.password_reset_by_admin' => '重置用户密码',
                            'user.sessions_revoked' => '强制退出用户会话',
                            default => $log->action,
                        },
                        'time' => $log->created_at->timezone('Asia/Shanghai')->format('m-d H:i'),
                    ])->all(),
                ];
            }

            return $snapshot;
        });

        if ($admin) {
            $snapshot['applications'] = SubmissionApplication::where('status', 'pending')->count();
        }

        $overview = [
            ['label' => '图片总数', 'count' => $snapshot['photos'], 'url' => PhotoResource::getUrl()],
            ['label' => '已发布图片', 'count' => $snapshot['published'], 'url' => $this->filteredUrl(PhotoResource::class, ['status' => 'published'])],
            ['label' => '相册总数', 'count' => $snapshot['albums'], 'url' => AlbumResource::getUrl()],
        ];
        $todos = [
            ['label' => '待审投稿', 'count' => $snapshot['submissions'], 'url' => $this->filteredUrl(PhotoSubmissionResource::class, ['status' => 'pending'])],
        ];
        $processing = [];

        if ($admin) {
            $overview[] = ['label' => '用户总数', 'count' => $snapshot['users'], 'url' => UserResource::getUrl()];
            foreach ([['待审纠错', 'corrections', CommentResource::class, ['status' => 'pending', 'type' => 'correction']], ['待处理举报', 'reports', ReportResource::class, ['status' => 'pending']], ['待审投稿资格', 'applications', SubmissionApplicationResource::class, ['status' => 'pending']]] as [$label, $key, $resource, $filters]) {
                $todos[] = ['label' => $label, 'count' => $snapshot[$key], 'url' => $this->filteredUrl($resource, $filters)];
            }
            foreach (['pending' => '待处理', 'running' => '处理中', 'failed' => '失败任务'] as $status => $label) {
                $processing[] = ['label' => $label, 'count' => (int) ($snapshot['processing'][$status] ?? 0), 'url' => $this->filteredUrl(ProcessingJobResource::class, ['status' => $status]), 'failed' => $status === 'failed'];
            }
        }

        return compact('snapshot', 'overview', 'todos', 'processing', 'admin');
    }

    private function filteredUrl(string $resource, array $filters): string
    {
        return $resource::getUrl('index', ['filters' => array_map(fn (string $value): array => ['value' => $value], $filters)]);
    }
}
