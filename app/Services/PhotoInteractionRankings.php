<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Photo;
use App\Models\PhotoFavorite;
use App\Models\PhotoLike;
use App\Models\PhotoShare;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

class PhotoInteractionRankings
{
    private const DEFAULT_TYPE = 'hot';

    private const DEFAULT_WINDOW = 'all';

    private const PUBLIC_COPYRIGHT_EXCLUDED = ['restricted', 'remove_requested'];

    public function __construct(private readonly PublicHomepage $homepage, private readonly PublicSeo $seo) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function publicPayload(array $filters): array
    {
        $type = $this->normalizeType($filters['type'] ?? null);
        $window = $this->normalizeWindow($filters['window'] ?? null);
        $items = $this->rankedPhotos($type, $window, 24);

        return array_merge($this->homepage->shell(), [
            'seo' => $this->seo->rankings([
                'score_label' => $this->typeOptions()[$type] ?? '综合热度',
            ]),
            'filters' => [
                'type' => $type,
                'window' => $window,
            ],
            'options' => [
                'types' => $this->typeOptions(),
                'windows' => $this->windowOptions(),
            ],
            'summary' => [
                'total_ranked' => count($items),
                'score_label' => $this->typeOptions()[$type] ?? '综合热度',
                'window_label' => $this->windowOptions()[$window] ?? '全部时间',
                'formula' => '收藏 x4 + 点赞 x3 + 评论 x2 + 分享 x1',
            ],
            'items' => $items,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminRows(?string $type = null, ?string $window = null, int $limit = 50): array
    {
        return $this->rankedPhotos(
            $this->normalizeType($type),
            $this->normalizeWindow($window),
            $limit,
        );
    }

    /**
     * @return array<string, string>
     */
    public function typeOptions(): array
    {
        return [
            'hot' => '综合热度',
            'likes' => '点赞榜',
            'favorites' => '收藏榜',
            'comments' => '评论榜',
            'shares' => '分享榜',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function windowOptions(): array
    {
        return [
            'all' => '全部时间',
            '7d' => '近 7 天',
            '30d' => '近 30 天',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rankedPhotos(string $type, string $window, int $limit): array
    {
        $sortColumn = $this->sortColumn($type);

        return $this->rankingQuery($window)
            ->orderByDesc($sortColumn)
            ->orderByDesc('photos.published_at')
            ->orderByDesc('photos.id')
            ->limit(max($limit, 100))
            ->get()
            ->filter(fn (Photo $photo): bool => (int) $photo->getAttribute($sortColumn) > 0)
            ->take($limit)
            ->values()
            ->map(fn (Photo $photo, int $index): array => $this->photoRow($photo, $index + 1))
            ->all();
    }

    private function rankingQuery(string $window): Builder
    {
        $since = $this->since($window);

        return Photo::query()
            ->with(['categories.parent'])
            ->published()
            ->whereNotIn('copyright_status', self::PUBLIC_COPYRIGHT_EXCLUDED)
            ->leftJoinSub($this->interactionCounts(PhotoLike::query(), $since), 'ranking_likes', 'ranking_likes.photo_id', '=', 'photos.id')
            ->leftJoinSub($this->interactionCounts(PhotoFavorite::query(), $since), 'ranking_favorites', 'ranking_favorites.photo_id', '=', 'photos.id')
            ->leftJoinSub($this->interactionCounts(PhotoShare::query(), $since), 'ranking_shares', 'ranking_shares.photo_id', '=', 'photos.id')
            ->leftJoinSub($this->commentCounts($since), 'ranking_comments', 'ranking_comments.photo_id', '=', 'photos.id')
            ->select('photos.*')
            ->selectRaw('COALESCE(ranking_likes.total_count, 0) as likes_count')
            ->selectRaw('COALESCE(ranking_favorites.total_count, 0) as favorites_count')
            ->selectRaw('COALESCE(ranking_comments.total_count, 0) as comments_count')
            ->selectRaw('COALESCE(ranking_shares.total_count, 0) as shares_count')
            ->selectRaw('(COALESCE(ranking_favorites.total_count, 0) * 4 + COALESCE(ranking_likes.total_count, 0) * 3 + COALESCE(ranking_comments.total_count, 0) * 2 + COALESCE(ranking_shares.total_count, 0)) as hot_score');
    }

    /**
     * @param  Builder<PhotoLike|PhotoFavorite|PhotoShare>  $query
     */
    private function interactionCounts(Builder $query, ?Carbon $since): QueryBuilder
    {
        return $query
            ->when($since, fn (Builder $query): Builder => $query->where('created_at', '>=', $since))
            ->selectRaw('photo_id, count(*) as total_count')
            ->groupBy('photo_id')
            ->toBase();
    }

    private function commentCounts(?Carbon $since): QueryBuilder
    {
        return Comment::query()
            ->published()
            ->discussion()
            ->when($since, fn (Builder $query): Builder => $query->where('created_at', '>=', $since))
            ->selectRaw('photo_id, count(*) as total_count')
            ->groupBy('photo_id')
            ->toBase();
    }

    /**
     * @return array<string, mixed>
     */
    private function photoRow(Photo $photo, int $rank): array
    {
        $likes = (int) $photo->getAttribute('likes_count');
        $favorites = (int) $photo->getAttribute('favorites_count');
        $comments = (int) $photo->getAttribute('comments_count');
        $shares = (int) $photo->getAttribute('shares_count');
        $hotScore = (int) $photo->getAttribute('hot_score');

        return [
            'rank' => $rank,
            'id' => $photo->id,
            'uuid' => $photo->uuid,
            'title' => $photo->title,
            'url' => '/photos/'.$photo->uuid,
            'admin_url' => '/admin/photos',
            'image_url' => PublicMediaUrl::fromPublicDisk($photo->display_key ?: $photo->thumbnail_key),
            'alt' => $photo->title,
            'event_date' => $photo->event_date?->toDateString(),
            'published_at' => $photo->published_at?->toDateTimeString(),
            'category_summary' => $this->categorySummary($photo),
            'metrics' => [
                'hot_score' => $hotScore,
                'likes' => $likes,
                'favorites' => $favorites,
                'comments' => $comments,
                'shares' => $shares,
            ],
        ];
    }

    private function categorySummary(Photo $photo): string
    {
        $summary = $photo->categories
            ->sortBy(fn ($category): string => ($category->parent?->name ?? '').$category->name)
            ->take(3)
            ->map(fn ($category): string => $category->parent ? $category->parent->name.' / '.$category->name : $category->name)
            ->implode(' · ');

        return $summary !== '' ? $summary : '分类待补充';
    }

    private function sortColumn(string $type): string
    {
        return match ($type) {
            'likes' => 'likes_count',
            'favorites' => 'favorites_count',
            'comments' => 'comments_count',
            'shares' => 'shares_count',
            default => 'hot_score',
        };
    }

    private function normalizeType(mixed $type): string
    {
        return is_string($type) && array_key_exists($type, $this->typeOptions()) ? $type : self::DEFAULT_TYPE;
    }

    private function normalizeWindow(mixed $window): string
    {
        return is_string($window) && array_key_exists($window, $this->windowOptions()) ? $window : self::DEFAULT_WINDOW;
    }

    private function since(string $window): ?Carbon
    {
        return match ($window) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => null,
        };
    }
}
