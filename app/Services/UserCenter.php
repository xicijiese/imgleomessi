<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\PhotoFavorite;
use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;

class UserCenter
{
    public function __construct(private readonly HomepageSettings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function shell(User $user): array
    {
        $settings = $this->settings->formState();

        return [
            'site' => $this->site($settings),
            'navigation' => $this->navigation($settings),
            'user' => $this->profile($user),
            'summary' => $this->summary($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(User $user): array
    {
        return [
            ...$this->shell($user),
            'recent_favorites' => $user->photoFavorites()
                ->with(['photo.categories.parent', 'photo.tags'])
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (PhotoFavorite $favorite): array => $this->favoriteItem($favorite))
                ->all(),
            'recent_comments' => $user->comments()
                ->with('photo')
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (Comment $comment): array => $this->commentItem($comment))
                ->all(),
            'recent_notifications' => $user->notifications()
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => $this->notificationItem($notification))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function favorites(User $user, array $filters = []): array
    {
        $query = $user->photoFavorites()
            ->with(['photo.categories.parent', 'photo.tags'])
            ->latest();

        if (filled($filters['q'] ?? null)) {
            $keyword = (string) $filters['q'];
            $query->whereHas('photo', fn ($photo) => $photo->where('title', 'like', '%'.$keyword.'%'));
        }

        if (filled($filters['category_id'] ?? null)) {
            $categoryId = (int) $filters['category_id'];
            $query->whereHas('photo.categories', fn ($category) => $category->whereKey($categoryId));
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $paginator = $query->paginate(12)->withQueryString();

        return [
            ...$this->shell($user),
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'category_id' => filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null,
                'date_from' => (string) ($filters['date_from'] ?? ''),
                'date_to' => (string) ($filters['date_to'] ?? ''),
            ],
            'filter_options' => [
                'categories' => $this->categoryOptions(),
            ],
            'favorites' => $this->paginate($paginator, fn (PhotoFavorite $favorite): array => $this->favoriteItem($favorite)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function comments(User $user, array $filters = []): array
    {
        $query = $user->comments()
            ->with('photo')
            ->latest();

        if (filled($filters['type'] ?? null) && array_key_exists($filters['type'], Comment::TYPES)) {
            $query->where('type', $filters['type']);
        }

        if (filled($filters['status'] ?? null) && array_key_exists($filters['status'], Comment::STATUSES)) {
            $query->where('status', $filters['status']);
        }

        $paginator = $query->paginate(10)->withQueryString();

        return [
            ...$this->shell($user),
            'filters' => [
                'type' => (string) ($filters['type'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
            ],
            'options' => [
                'types' => $this->options(Comment::TYPES),
                'statuses' => $this->options(Comment::STATUSES),
            ],
            'comments' => $this->paginate($paginator, fn (Comment $comment): array => $this->commentItem($comment)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function reports(User $user, array $filters = []): array
    {
        $query = $user->submittedReports()
            ->with(['comment.photo', 'comment.user'])
            ->latest();

        if (filled($filters['status'] ?? null) && array_key_exists($filters['status'], Report::STATUSES)) {
            $query->where('status', $filters['status']);
        }

        $paginator = $query->paginate(10)->withQueryString();

        return [
            ...$this->shell($user),
            'filters' => [
                'status' => (string) ($filters['status'] ?? ''),
            ],
            'options' => [
                'statuses' => $this->options(Report::STATUSES),
                'reasons' => $this->options(Report::REASONS),
            ],
            'reports' => $this->paginate($paginator, fn (Report $report): array => $this->reportItem($report)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function notifications(User $user, array $filters = []): array
    {
        $query = $user->notifications()->latest();

        if (($filters['status'] ?? null) === 'unread') {
            $query->whereNull('read_at');
        }

        if (($filters['status'] ?? null) === 'read') {
            $query->whereNotNull('read_at');
        }

        if (filled($filters['type'] ?? null)) {
            $query->where('data->category', $filters['type']);
        }

        $paginator = $query->paginate(10)->withQueryString();

        return [
            ...$this->shell($user),
            'filters' => [
                'status' => (string) ($filters['status'] ?? ''),
                'type' => (string) ($filters['type'] ?? ''),
            ],
            'options' => [
                'statuses' => [
                    ['value' => 'unread', 'label' => '未读'],
                    ['value' => 'read', 'label' => '已读'],
                ],
                'types' => [
                    ['value' => 'comment_review', 'label' => '评论审核'],
                    ['value' => 'report_result', 'label' => '举报处理'],
                    ['value' => 'account_status', 'label' => '账号状态'],
                    ['value' => 'system', 'label' => '系统通知'],
                ],
            ],
            'notifications' => $this->paginate($paginator, fn (DatabaseNotification $notification): array => $this->notificationItem($notification)),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function site(array $settings): array
    {
        $site = Arr::get($settings, 'site', []);

        return [
            'name' => $site['name'] ?? '梅西影像档案库',
            'logo_url' => $this->mediaUrl($site['logo_path'] ?? null),
            'search_placeholder' => $site['search_placeholder'] ?? '搜索图片、相册、赛事或年份',
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array{label: string, url: string}>
     */
    private function navigation(array $settings): array
    {
        return collect(Arr::get($settings, 'navigation.items', []))
            ->filter(fn (array $item): bool => (bool) ($item['enabled'] ?? false))
            ->map(fn (array $item): array => [
                'label' => (string) ($item['label'] ?? ''),
                'url' => (string) ($item['url'] ?? '#'),
            ])
            ->filter(fn (array $item): bool => filled($item['label']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status ?? 'active',
            'status_label' => User::STATUSES[$user->status ?? 'active'] ?? '正常',
            'is_banned' => $user->isBanned(),
            'banned_until' => $user->banned_until?->format('Y-m-d H:i'),
            'ban_reason' => $user->ban_reason,
            'created_at' => $user->created_at?->format('Y-m-d'),
            'profile_public' => $user->profile_public,
            'profile_bio' => $user->profile_bio,
            'public_profile_url' => '/users/'.$user->id,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summary(User $user): array
    {
        return [
            'favorites_count' => $user->photoFavorites()->count(),
            'comments_count' => $user->comments()->discussion()->count(),
            'corrections_count' => $user->comments()->correction()->count(),
            'reports_count' => $user->submittedReports()->count(),
            'pending_count' => $user->comments()->pendingReview()->count() + $user->submittedReports()->pending()->count(),
            'unread_notifications_count' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function favoriteItem(PhotoFavorite $favorite): array
    {
        return [
            'id' => $favorite->id,
            'created_at' => $favorite->created_at?->format('Y-m-d H:i'),
            'photo' => $this->photoSummary($favorite->photo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commentItem(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'type' => $comment->type,
            'type_label' => Comment::TYPES[$comment->type] ?? '内容',
            'status' => $comment->status,
            'status_label' => Comment::STATUSES[$comment->status] ?? '未知',
            'content' => $comment->content,
            'correction_field' => $comment->correction_field,
            'correction_field_label' => $comment->correction_field ? (Comment::CORRECTION_FIELDS[$comment->correction_field] ?? $comment->correction_field) : null,
            'suggested_value' => $comment->suggested_value,
            'evidence_url' => $comment->evidence_url,
            'result_message' => $this->commentResultMessage($comment),
            'created_at' => $comment->created_at?->format('Y-m-d H:i'),
            'reviewed_at' => $comment->reviewed_at?->format('Y-m-d H:i'),
            'photo' => $this->photoSummary($comment->photo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportItem(Report $report): array
    {
        return [
            'id' => $report->id,
            'reason' => $report->reason,
            'reason_label' => Report::REASONS[$report->reason] ?? '其他',
            'status' => $report->status,
            'status_label' => Report::STATUSES[$report->status] ?? '未知',
            'details' => $report->details,
            'result_message' => $this->reportResultMessage($report),
            'created_at' => $report->created_at?->format('Y-m-d H:i'),
            'handled_at' => $report->handled_at?->format('Y-m-d H:i'),
            'target' => [
                'type' => $report->target_type,
                'type_label' => Report::TARGET_TYPES[$report->target_type] ?? '内容',
                'comment_excerpt' => str((string) $report->comment?->content)->limit(90)->toString(),
                'comment_user_name' => $report->comment?->user?->name,
                'photo' => $this->photoSummary($report->comment?->photo),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationItem(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'category' => (string) ($data['category'] ?? 'system'),
            'title' => (string) ($data['title'] ?? '系统通知'),
            'message' => (string) ($data['message'] ?? ''),
            'url' => $data['url'] ?? null,
            'target_type' => $data['target_type'] ?? null,
            'target_id' => $data['target_id'] ?? null,
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->format('Y-m-d H:i'),
            'created_at' => $notification->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photoSummary(?Photo $photo): array
    {
        if (! $photo instanceof Photo || ! $this->isPublicPhoto($photo)) {
            return [
                'is_available' => false,
                'title' => '内容暂不可见',
                'url' => null,
                'image_url' => null,
                'category_summary' => null,
            ];
        }

        return [
            'is_available' => true,
            'id' => $photo->id,
            'uuid' => $photo->uuid,
            'title' => $photo->title,
            'url' => '/photos/'.$photo->uuid,
            'image_url' => $this->mediaUrl($photo->display_key ?: $photo->thumbnail_key),
            'category_summary' => $photo->categories
                ->take(3)
                ->map(fn ($category): string => $category->parent?->name ? $category->parent->name.' / '.$category->name : $category->name)
                ->implode(' · '),
        ];
    }

    private function isPublicPhoto(Photo $photo): bool
    {
        return $photo->status === 'published'
            && ! in_array($photo->copyright_status, ['restricted', 'remove_requested'], true);
    }

    private function commentResultMessage(Comment $comment): string
    {
        return match ($comment->status) {
            'pending' => '内容已提交，正在等待审核。',
            'published' => '内容已通过审核并记录。',
            'rejected' => '内容未通过审核。',
            'hidden' => '内容已被隐藏。',
            'deleted' => '内容已被移除。',
            default => '处理状态已更新。',
        };
    }

    private function reportResultMessage(Report $report): string
    {
        return match ($report->status) {
            'pending' => '举报已提交，正在等待处理。',
            'resolved' => '举报已处理，感谢帮助维护社区秩序。',
            'rejected' => '举报已驳回，处理结果已记录。',
            'closed' => '举报已关闭。',
            default => '处理状态已更新。',
        };
    }

    /**
     * @param  array<string, string>  $items
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $items): array
    {
        return collect($items)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, group: string|null}>
     */
    private function categoryOptions(): array
    {
        return Category::query()
            ->children()
            ->where('visibility', 'public')
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'group' => $category->parent?->name,
            ])
            ->all();
    }

    /**
     * @template TModel
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  callable(TModel): array<string, mixed>  $mapper
     * @return array<string, mixed>
     */
    private function paginate(LengthAwarePaginator $paginator, callable $mapper): array
    {
        return [
            'data' => collect($paginator->items())->map($mapper)->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }

    private function mediaUrl(mixed $path): ?string
    {
        return PublicMediaUrl::fromPublicDisk($path);
    }
}
