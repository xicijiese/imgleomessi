<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicTimeline
{
    private const PER_PAGE = 24;

    private const SORTS = [
        'event_desc' => '事件日期新到旧',
        'event_asc' => '事件日期旧到新',
        'published_desc' => '最新发布',
        'hot_desc' => '综合热度',
    ];

    public function __construct(private readonly HomepageSettings $settings, private readonly PublicSeo $seo) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Request $request): array
    {
        $years = $this->yearSummaries();

        return [
            'site' => $this->site(),
            'navigation' => $this->navigation(),
            'seo' => $this->seo->timelineIndex(),
            'scope' => [
                'type' => 'index',
                'title' => '时间线',
                'subtitle' => '按事件日期浏览已公开的梅西影像资料。',
            ],
            'summary' => [
                'years_count' => count($years),
                'photos_count' => (clone $this->publicDatedPhotosQuery())->count(),
                'albums_count' => $this->publicAlbumsQuery()->count(),
            ],
            'years' => $years,
            'latest_photos' => (clone $this->publicDatedPhotosQuery())
                ->with(['albums' => fn ($query) => $query->where('albums.status', 'published')->orderBy('albums.sort_order')->orderBy('albums.id'), 'categories.parent', 'tags'])
                ->orderByDesc('event_date')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(12)
                ->get()
                ->map(fn (Photo $photo): array => $this->photoCard($photo))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function yearPayload(Request $request, int $year): array
    {
        $this->abortIfInvalidYear($year);

        $filters = $this->filters($request);
        $query = $this->filteredPhotosQuery($filters)->whereYear('event_date', $year);
        $this->applySort($query, $filters['sort']);

        /** @var LengthAwarePaginator<int, Photo> $paginator */
        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();
        $albums = $this->albumCardsForPeriod($year, null, $filters);

        return [
            'site' => $this->site(),
            'navigation' => $this->navigation(),
            'seo' => $this->seo->timelineYear($year),
            'scope' => [
                'type' => 'year',
                'title' => $year.' 年时间线',
                'subtitle' => '查看 '.$year.' 年已标注事件日期的公开图片和相关相册。',
                'year' => $year,
                'month' => null,
                'breadcrumbs' => [
                    ['label' => '时间线', 'url' => '/timeline'],
                    ['label' => $year.' 年', 'url' => '/timeline/'.$year],
                ],
            ],
            'summary' => [
                'photos_count' => $paginator->total(),
                'albums_count' => count($albums),
                'months_count' => count($this->monthSummaries($year, $filters)),
            ],
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'months' => $this->monthSummaries($year, $filters),
            'photos' => $this->paginatedPhotos($paginator),
            'albums' => $albums,
            'related_topics' => $this->relatedTopics(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function monthPayload(Request $request, int $year, int $month): array
    {
        $this->abortIfInvalidYear($year);
        $this->abortIfInvalidMonth($month);

        $filters = $this->filters($request);
        $query = $this->filteredPhotosQuery($filters)
            ->whereYear('event_date', $year)
            ->whereMonth('event_date', $month);
        $this->applySort($query, $filters['sort']);

        /** @var LengthAwarePaginator<int, Photo> $paginator */
        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();
        $albums = $this->albumCardsForPeriod($year, $month, $filters);
        $monthLabel = $this->monthLabel($month);

        return [
            'site' => $this->site(),
            'navigation' => $this->navigation(),
            'seo' => $this->seo->timelineMonth($year, $month),
            'scope' => [
                'type' => 'month',
                'title' => $year.' 年 '.$monthLabel.' 时间线',
                'subtitle' => '查看 '.$year.' 年 '.$monthLabel.' 已标注事件日期的公开图片和相关相册。',
                'year' => $year,
                'month' => $month,
                'month_label' => $monthLabel,
                'breadcrumbs' => [
                    ['label' => '时间线', 'url' => '/timeline'],
                    ['label' => $year.' 年', 'url' => '/timeline/'.$year],
                    ['label' => $monthLabel, 'url' => '/timeline/'.$year.'/'.str_pad((string) $month, 2, '0', STR_PAD_LEFT)],
                ],
            ],
            'summary' => [
                'photos_count' => $paginator->total(),
                'albums_count' => count($albums),
                'months_count' => 1,
            ],
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'months' => $this->monthSummaries($year, $filters),
            'photos' => $this->paginatedPhotos($paginator),
            'albums' => $albums,
            'related_topics' => $this->relatedTopics(),
        ];
    }

    private function abortIfInvalidYear(int $year): void
    {
        if ($year < 1900 || $year > now()->year + 1) {
            abort(404);
        }
    }

    private function abortIfInvalidMonth(int $month): void
    {
        if ($month < 1 || $month > 12) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'categories' => $this->selectedCategories($request),
            'tags' => $this->selectedTags($request),
            'sort' => $this->sortValue($request->query('sort')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredPhotosQuery(array $filters): Builder
    {
        $query = $this->publicDatedPhotosQuery()
            ->with([
                'albums' => fn ($query) => $query->where('albums.status', 'published')->orderBy('albums.sort_order')->orderBy('albums.id'),
                'categories.parent',
                'tags',
            ])
            ->withCount([
                'favorites',
                'likes',
                'shares',
                'comments as published_comments_count' => fn (Builder $query): Builder => $query
                    ->where('type', 'discussion')
                    ->where('status', 'published'),
            ]);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredDatedPhotosQuery(array $filters): Builder
    {
        $query = $this->publicDatedPhotosQuery();
        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['q'])) {
            $keyword = str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']);
            $query->where(function (Builder $query) use ($keyword): void {
                $query->where('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        foreach ($filters['categories'] as $categoryId) {
            $query->whereHas('categories', fn (Builder $query): Builder => $query->where('categories.id', $categoryId));
        }

        foreach ($filters['tags'] as $tagId) {
            $query->whereHas('tags', fn (Builder $query): Builder => $query->where('tags.id', $tagId));
        }
    }

    private function publicDatedPhotosQuery(): Builder
    {
        return Photo::query()
            ->published()
            ->whereNotNull('event_date')
            ->whereNotIn('copyright_status', ['restricted', 'remove_requested']);
    }

    private function publicAlbumsQuery(): Builder
    {
        return Album::query()
            ->published()
            ->whereHas('photos', fn (Builder $query): Builder => $this->publicDatedPhotoConstraint($query));
    }

    private function publicPhotoConstraint($query)
    {
        return $query
            ->where('photos.status', 'published')
            ->whereNotIn('photos.copyright_status', ['restricted', 'remove_requested']);
    }

    private function publicDatedPhotoConstraint($query)
    {
        return $this->publicPhotoConstraint($query)->whereNotNull('photos.event_date');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function yearSummaries(): array
    {
        return (clone $this->publicDatedPhotosQuery())
            ->orderByDesc('event_date')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'uuid', 'title', 'display_key', 'thumbnail_key', 'event_date', 'published_at'])
            ->groupBy(fn (Photo $photo): string => $photo->event_date?->format('Y') ?? '')
            ->filter(fn ($photos, string $year): bool => $year !== '')
            ->map(function ($photos, string $year): array {
                $cover = $photos->first();
                $monthCounts = $photos
                    ->groupBy(fn (Photo $photo): string => $photo->event_date?->format('n') ?? '')
                    ->filter(fn ($monthPhotos, string $month): bool => $month !== '')
                    ->map(fn ($monthPhotos, string $month): array => [
                        'month' => (int) $month,
                        'label' => $this->monthLabel((int) $month),
                        'url' => '/timeline/'.$year.'/'.str_pad($month, 2, '0', STR_PAD_LEFT),
                        'photos_count' => $monthPhotos->count(),
                    ])
                    ->sortByDesc('month')
                    ->values()
                    ->all();

                return [
                    'year' => (int) $year,
                    'url' => '/timeline/'.$year,
                    'photos_count' => $photos->count(),
                    'albums_count' => $this->albumsForPeriodQuery((int) $year)->count(),
                    'cover_image_url' => $cover instanceof Photo ? $this->mediaUrl($cover->display_key ?: $cover->thumbnail_key) : null,
                    'cover_alt' => $cover instanceof Photo ? $cover->title : $year.' 年时间线',
                    'months' => $monthCounts,
                ];
            })
            ->sortByDesc('year')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function monthSummaries(int $year, array $filters): array
    {
        return $this->filteredDatedPhotosQuery($filters)
            ->whereYear('event_date', $year)
            ->orderByDesc('event_date')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'display_key', 'thumbnail_key', 'event_date', 'published_at'])
            ->groupBy(fn (Photo $photo): string => $photo->event_date?->format('n') ?? '')
            ->filter(fn ($photos, string $month): bool => $month !== '')
            ->map(function ($photos, string $month) use ($year): array {
                $cover = $photos->first();

                return [
                    'month' => (int) $month,
                    'label' => $this->monthLabel((int) $month),
                    'url' => '/timeline/'.$year.'/'.str_pad($month, 2, '0', STR_PAD_LEFT),
                    'photos_count' => $photos->count(),
                    'cover_image_url' => $cover instanceof Photo ? $this->mediaUrl($cover->display_key ?: $cover->thumbnail_key) : null,
                    'cover_alt' => $cover instanceof Photo ? $cover->title : $year.' 年 '.$this->monthLabel((int) $month),
                ];
            })
            ->sortByDesc('month')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    private function albumsForPeriodQuery(int $year, ?int $month = null, ?array $filters = null): Builder
    {
        $query = Album::query()
            ->published()
            ->whereHas('photos', fn (Builder $query): Builder => $this->periodPhotoConstraint($query, $year, $month))
            ->withCount([
                'photos as public_photos_count' => fn (Builder $query): Builder => $this->periodPhotoConstraint($query, $year, $month),
            ])
            ->with([
                'categories.parent',
                'photos' => fn ($query) => $this->periodPhotoConstraint($query, $year, $month)
                    ->orderByDesc('photos.event_date')
                    ->orderByDesc('photos.published_at')
                    ->orderByDesc('photos.id'),
            ]);

        if ($filters !== null) {
            if (filled($filters['q'])) {
                $keyword = str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']);
                $query->where(function (Builder $query) use ($keyword): void {
                    $query->where('title', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%")
                        ->orWhere('slug', 'like', "%{$keyword}%");
                });
            }

            foreach ($filters['categories'] as $categoryId) {
                $query->whereHas('categories', fn (Builder $query): Builder => $query->where('categories.id', $categoryId));
            }

            foreach ($filters['tags'] as $tagId) {
                $query->whereHas('photos', fn (Builder $query): Builder => $this->periodPhotoConstraint($query, $year, $month)
                    ->whereHas('tags', fn (Builder $query): Builder => $query->where('tags.id', $tagId)));
            }
        }

        return $query;
    }

    private function periodPhotoConstraint($query, int $year, ?int $month)
    {
        $query = $this->publicDatedPhotoConstraint($query)->whereYear('photos.event_date', $year);

        if ($month !== null) {
            $query->whereMonth('photos.event_date', $month);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function albumCardsForPeriod(int $year, ?int $month, array $filters): array
    {
        return $this->albumsForPeriodQuery($year, $month, $filters)
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Album $album): array => $this->albumCard($album))
            ->values()
            ->all();
    }

    /**
     * @param  LengthAwarePaginator<int, Photo>  $paginator
     * @return array<string, mixed>
     */
    private function paginatedPhotos(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()
                ->map(fn (Photo $photo): array => $this->photoCard($photo))
                ->values()
                ->all(),
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

    /**
     * @return array<string, int>
     */
    private function selectedCategories(Request $request): array
    {
        $rawCategories = $request->query('categories', []);

        if (! is_array($rawCategories)) {
            return [];
        }

        $selected = [];

        foreach ($rawCategories as $rootSlug => $categoryId) {
            if (! is_string($rootSlug) || blank($categoryId)) {
                continue;
            }

            $category = Category::query()
                ->children()
                ->where('id', (int) $categoryId)
                ->where('visibility', 'public')
                ->whereHas('parent', fn (Builder $query): Builder => $query
                    ->where('slug', $rootSlug)
                    ->where('visibility', 'public'))
                ->first();

            if ($category instanceof Category) {
                $selected[$rootSlug] = $category->id;
            }
        }

        return $selected;
    }

    /**
     * @return array<int, int>
     */
    private function selectedTags(Request $request): array
    {
        $tagIds = collect(Arr::wrap($request->query('tags', [])))
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($tagIds->isEmpty()) {
            return [];
        }

        return Tag::query()
            ->whereIn('id', $tagIds)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->values()
            ->all();
    }

    private function sortValue(mixed $value): string
    {
        if (is_string($value) && array_key_exists($value, self::SORTS)) {
            return $value;
        }

        return 'event_desc';
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'event_asc' => $query->orderBy('event_date')->orderBy('published_at')->orderBy('id'),
            'published_desc' => $query->orderByDesc('published_at')->orderByDesc('created_at')->orderByDesc('id'),
            'hot_desc' => $query->orderByRaw('(favorites_count * 4 + likes_count * 3 + published_comments_count * 2 + shares_count) desc')->orderByDesc('event_date')->orderByDesc('id'),
            default => $query->orderByDesc('event_date')->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'category_groups' => Category::query()
                ->roots()
                ->where('visibility', 'public')
                ->with(['children' => fn ($query) => $query->where('visibility', 'public')->orderBy('sort_order')->orderBy('id')])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'children' => $category->children->map(fn (Category $child): array => [
                        'id' => $child->id,
                        'name' => $child->name,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
            'tags' => Tag::query()
                ->orderBy('type')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'type'])
                ->map(fn (Tag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'type' => $tag->type,
                ])
                ->values()
                ->all(),
            'sorts' => collect(self::SORTS)
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photoCard(Photo $photo): array
    {
        $categories = $photo->categories
            ->filter(fn (Category $category): bool => $category->parent instanceof Category)
            ->sortBy(fn (Category $category): string => ($category->parent?->sort_order ?? 0).'-'.$category->sort_order.'-'.$category->id)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'root_name' => $category->parent?->name,
                'root_slug' => $category->parent?->slug,
            ])
            ->values();

        $tags = $photo->tags
            ->sortBy(fn (Tag $tag): string => $tag->type.'-'.$tag->sort_order.'-'.$tag->id)
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'type' => $tag->type,
            ])
            ->values();

        return [
            'id' => $photo->id,
            'uuid' => $photo->uuid,
            'title' => $photo->title,
            'description' => Str::limit((string) $photo->description, 96),
            'url' => '/photos/'.$photo->uuid,
            'image_url' => $this->mediaUrl($photo->display_key ?: $photo->thumbnail_key),
            'alt' => filled($photo->title) ? $photo->title : '梅西图片档案',
            'event_date' => $photo->event_date?->toDateString(),
            'published_at' => $photo->published_at?->toDateString(),
            'category_summary' => $categories->pluck('name')->take(3)->implode(' / '),
            'categories' => $categories->all(),
            'tags' => $tags->all(),
            'albums' => $photo->albums->map(fn (Album $album): array => [
                'id' => $album->id,
                'title' => $album->title,
                'url' => '/albums/'.$album->slug,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function albumCard(Album $album): array
    {
        $categories = $album->categories
            ->filter(fn (Category $category): bool => $category->parent instanceof Category)
            ->sortBy(fn (Category $category): string => ($category->parent?->sort_order ?? 0).'-'.$category->sort_order.'-'.$category->id)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'root_name' => $category->parent?->name,
                'root_slug' => $category->parent?->slug,
            ])
            ->values();

        $coverPhoto = $album->photos->firstWhere('id', $album->cover_photo_id) ?? $album->photos->first();

        return [
            'id' => $album->id,
            'title' => $album->title,
            'slug' => $album->slug,
            'description' => Str::limit((string) $album->description, 120),
            'url' => '/albums/'.$album->slug,
            'cover_image_url' => $coverPhoto instanceof Photo ? $this->mediaUrl($coverPhoto->display_key ?: $coverPhoto->thumbnail_key) : null,
            'cover_alt' => $album->title,
            'published_at' => $album->published_at?->toDateString(),
            'public_photos_count' => (int) ($album->public_photos_count ?? 0),
            'category_summary' => $categories->pluck('name')->take(3)->implode(' / '),
            'categories' => $categories->all(),
        ];
    }

    /**
     * @return array<int, array{title: string, url: string, description: string}>
     */
    private function relatedTopics(): array
    {
        return collect(Arr::get($this->settings->formState(), 'topic_module.items', []))
            ->filter(fn (array $topic): bool => (bool) ($topic['enabled'] ?? true) && filled($topic['title'] ?? null) && filled($topic['url'] ?? null))
            ->take(4)
            ->map(fn (array $topic): array => [
                'title' => (string) ($topic['title'] ?? ''),
                'url' => (string) ($topic['url'] ?? '#'),
                'description' => Str::limit((string) ($topic['description'] ?? ''), 96),
            ])
            ->values()
            ->all();
    }

    private function monthLabel(int $month): string
    {
        return str_pad((string) $month, 2, '0', STR_PAD_LEFT).' 月';
    }

    /**
     * @return array<string, mixed>
     */
    private function site(): array
    {
        $settings = $this->settings->formState();
        $site = Arr::get($settings, 'site', []);

        return [
            'name' => $site['name'] ?? '梅西影像档案库',
            'logo_url' => $this->mediaUrl($site['logo_path'] ?? null),
            'search_placeholder' => $site['search_placeholder'] ?? '搜索图片、相册、赛事或年份',
        ];
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function navigation(): array
    {
        $settings = $this->settings->formState();

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

    private function mediaUrl(mixed $path): ?string
    {
        return PublicMediaUrl::fromPublicDisk($path);
    }
}
