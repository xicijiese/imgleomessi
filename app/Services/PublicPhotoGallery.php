<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Source;
use App\Models\Tag;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicPhotoGallery
{
    private const PER_PAGE = 24;

    private const SORTS = [
        'published_desc' => '最新发布',
        'event_desc' => '事件日期新到旧',
        'event_asc' => '事件日期旧到新',
        'hot_desc' => '综合热度',
        'favorites_desc' => '最多收藏',
        'likes_desc' => '最多点赞',
        'comments_desc' => '最多评论',
    ];

    private const SOURCE_MODES = [
        'all' => '全部来源',
        'has' => '有来源',
        'none' => '无来源',
        'specific' => '指定来源',
    ];

    private const ORIENTATIONS = [
        'all' => '全部构图',
        'landscape' => '横图',
        'portrait' => '竖图',
        'square' => '方图',
        'unknown' => '未知',
    ];

    private const RESOLUTIONS = [
        'all' => '全部清晰度',
        'ultra' => '超清',
        'high' => '高清',
        'standard' => '普通',
        'unknown' => '未知',
    ];

    public function __construct(private readonly HomepageSettings $settings, private readonly PublicSeo $seo) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request): array
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);
        $this->applySort($query, $filters['sort']);

        /** @var LengthAwarePaginator<int, Photo> $paginator */
        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();

        return [
            'site' => $this->site(),
            'navigation' => $this->navigation(),
            'seo' => $this->seo->photosIndex(),
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'photos' => [
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
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $sourceId = $this->selectedSource($request);
        $sourceMode = $this->sourceModeValue($request->query('source_mode'), $sourceId);

        return [
            'q' => trim((string) $request->query('q', '')),
            'categories' => $this->selectedCategories($request),
            'tags' => $this->selectedTags($request, false),
            'people_tags' => $this->selectedTags($request, true),
            'album_id' => $this->selectedAlbum($request),
            'source_mode' => $sourceMode,
            'source_id' => $sourceMode === 'specific' ? $sourceId : null,
            'copyright_status' => $this->copyrightStatusValue($request->query('copyright_status')),
            'orientation' => $this->optionValue($request->query('orientation'), self::ORIENTATIONS),
            'resolution' => $this->optionValue($request->query('resolution'), self::RESOLUTIONS),
            'watermark_status' => $this->watermarkStatusValue($request->query('watermark_status')),
            'date_from' => $this->dateValue($request->query('date_from')),
            'date_to' => $this->dateValue($request->query('date_to')),
            'sort' => $this->sortValue($request->query('sort')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = $this->publicPhotosQuery()
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

        foreach ($filters['people_tags'] as $tagId) {
            $query->whereHas('tags', fn (Builder $query): Builder => $query
                ->where('tags.id', $tagId)
                ->where('tags.type', '人物关系'));
        }

        if ($filters['album_id'] !== null) {
            $query->whereHas('albums', fn (Builder $query): Builder => $query
                ->where('albums.id', $filters['album_id'])
                ->where('albums.status', 'published'));
        }

        $this->applySourceFilter($query, $filters['source_mode'], $filters['source_id']);

        if ($filters['copyright_status'] !== null) {
            $query->where('copyright_status', $filters['copyright_status']);
        }

        $this->applyOrientationFilter($query, $filters['orientation']);
        $this->applyResolutionFilter($query, $filters['resolution']);

        if ($filters['watermark_status'] !== null) {
            $query->where('watermark_status', $filters['watermark_status']);
        }

        if ($filters['date_from'] !== null) {
            $query->whereDate('event_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== null) {
            $query->whereDate('event_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    private function publicPhotosQuery(): Builder
    {
        return Photo::query()
            ->published()
            ->whereNotIn('copyright_status', ['restricted', 'remove_requested']);
    }

    private function publicPhotoConstraint($query)
    {
        return $query
            ->where('photos.status', 'published')
            ->whereNotIn('photos.copyright_status', ['restricted', 'remove_requested']);
    }

    private function applySourceFilter(Builder $query, string $sourceMode, ?int $sourceId): void
    {
        if ($sourceMode === 'has') {
            $query->whereNotNull('source_id');
        }

        if ($sourceMode === 'none') {
            $query->whereNull('source_id');
        }

        if ($sourceMode === 'specific' && $sourceId !== null) {
            $query->where('source_id', $sourceId);
        }
    }

    private function applyOrientationFilter(Builder $query, string $orientation): void
    {
        match ($orientation) {
            'landscape' => $query->whereNotNull('width')->whereNotNull('height')->whereColumn('width', '>', 'height'),
            'portrait' => $query->whereNotNull('width')->whereNotNull('height')->whereColumn('height', '>', 'width'),
            'square' => $query->whereNotNull('width')->whereNotNull('height')->whereColumn('width', 'height'),
            'unknown' => $query->where(fn (Builder $query): Builder => $query->whereNull('width')->orWhereNull('height')),
            default => null,
        };
    }

    private function applyResolutionFilter(Builder $query, string $resolution): void
    {
        match ($resolution) {
            'ultra' => $query->where(fn (Builder $query): Builder => $query->where('width', '>=', 3000)->orWhere('height', '>=', 3000)),
            'high' => $query
                ->where(fn (Builder $query): Builder => $query->where('width', '>=', 1920)->orWhere('height', '>=', 1920))
                ->where(fn (Builder $query): Builder => $query->where('width', '<', 3000)->where('height', '<', 3000)),
            'standard' => $query->whereNotNull('width')->whereNotNull('height')->where('width', '<', 1920)->where('height', '<', 1920),
            'unknown' => $query->where(fn (Builder $query): Builder => $query->whereNull('width')->orWhereNull('height')),
            default => null,
        };
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'event_desc' => $query->orderByDesc('event_date')->orderByDesc('published_at')->orderByDesc('id'),
            'event_asc' => $query->orderBy('event_date')->orderBy('published_at')->orderBy('id'),
            'hot_desc' => $query->orderByRaw('(favorites_count * 4 + likes_count * 3 + published_comments_count * 2 + shares_count) desc')->orderByDesc('published_at')->orderByDesc('id'),
            'favorites_desc' => $query->orderByDesc('favorites_count')->orderByDesc('published_at')->orderByDesc('id'),
            'likes_desc' => $query->orderByDesc('likes_count')->orderByDesc('published_at')->orderByDesc('id'),
            'comments_desc' => $query->orderByDesc('published_comments_count')->orderByDesc('published_at')->orderByDesc('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('created_at')->orderByDesc('id'),
        };
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
    private function selectedTags(Request $request, bool $peopleOnly): array
    {
        $key = $peopleOnly ? 'people_tags' : 'tags';
        $tagIds = collect(Arr::wrap($request->query($key, [])))
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($tagIds->isEmpty()) {
            return [];
        }

        $query = Tag::query()->whereIn('id', $tagIds);

        if ($peopleOnly) {
            $query->where('type', '人物关系');
        }

        return $query
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->values()
            ->all();
    }

    private function selectedAlbum(Request $request): ?int
    {
        $albumId = (int) $request->query('album_id', 0);

        if ($albumId <= 0) {
            return null;
        }

        $selectedAlbumId = Album::query()
            ->published()
            ->where('id', $albumId)
            ->whereHas('photos', fn (Builder $query): Builder => $this->publicPhotoConstraint($query))
            ->value('id');

        return $selectedAlbumId === null ? null : (int) $selectedAlbumId;
    }

    private function selectedSource(Request $request): ?int
    {
        $sourceId = (int) $request->query('source_id', 0);

        if ($sourceId <= 0) {
            return null;
        }

        $selectedSourceId = Source::query()
            ->enabled()
            ->where('id', $sourceId)
            ->value('id');

        return $selectedSourceId === null ? null : (int) $selectedSourceId;
    }

    private function dateValue(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    private function sortValue(mixed $value): string
    {
        if (is_string($value) && array_key_exists($value, self::SORTS)) {
            return $value;
        }

        return 'published_desc';
    }

    private function sourceModeValue(mixed $value, ?int $sourceId): string
    {
        if ($sourceId !== null) {
            return 'specific';
        }

        if (is_string($value) && in_array($value, ['has', 'none'], true)) {
            return $value;
        }

        return 'all';
    }

    /**
     * @param  array<string, string>  $options
     */
    private function optionValue(mixed $value, array $options): string
    {
        if (is_string($value) && array_key_exists($value, $options)) {
            return $value;
        }

        return 'all';
    }

    private function copyrightStatusValue(mixed $value): ?string
    {
        if (is_string($value) && array_key_exists($value, Photo::COPYRIGHT_STATUSES)) {
            return $value;
        }

        return null;
    }

    private function watermarkStatusValue(mixed $value): ?string
    {
        if (is_string($value) && array_key_exists($value, Photo::WATERMARK_STATUSES)) {
            return $value;
        }

        return null;
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
            'people_tags' => Tag::query()
                ->where('type', '人物关系')
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
            'albums' => Album::query()
                ->published()
                ->whereHas('photos', fn (Builder $query): Builder => $this->publicPhotoConstraint($query))
                ->orderBy('sort_order')
                ->orderByDesc('published_at')
                ->orderBy('id')
                ->get(['id', 'title'])
                ->map(fn (Album $album): array => [
                    'id' => $album->id,
                    'title' => $album->title,
                ])
                ->values()
                ->all(),
            'sources' => Source::query()
                ->enabled()
                ->latest('updated_at')
                ->get(['id', 'original_url'])
                ->map(fn (Source $source): array => [
                    'id' => $source->id,
                    'label' => $source->original_url ?: '来源 #'.$source->id,
                ])
                ->values()
                ->all(),
            'source_modes' => $this->keyValueOptions(self::SOURCE_MODES),
            'copyright_statuses' => $this->keyValueOptions(Photo::COPYRIGHT_STATUSES),
            'orientations' => $this->keyValueOptions(self::ORIENTATIONS),
            'resolutions' => $this->keyValueOptions(self::RESOLUTIONS),
            'watermark_statuses' => $this->keyValueOptions(Photo::WATERMARK_STATUSES),
            'sorts' => $this->keyValueOptions(self::SORTS),
        ];
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{value: string, label: string}>
     */
    private function keyValueOptions(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
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
            'resolution' => $this->resolutionValue($photo),
            'resolution_label' => self::RESOLUTIONS[$this->resolutionValue($photo)] ?? '未知',
            'orientation' => $this->orientationValue($photo),
            'orientation_label' => self::ORIENTATIONS[$this->orientationValue($photo)] ?? '未知',
            'watermark_status' => $photo->watermark_status,
            'watermark_status_label' => Photo::WATERMARK_STATUSES[$photo->watermark_status] ?? '未知',
            'albums' => $photo->albums->map(fn (Album $album): array => [
                'id' => $album->id,
                'title' => $album->title,
                'url' => '/albums/'.$album->slug,
            ])->values()->all(),
        ];
    }

    private function resolutionValue(Photo $photo): string
    {
        if ($photo->width === null || $photo->height === null) {
            return 'unknown';
        }

        $longEdge = max($photo->width, $photo->height);

        if ($longEdge >= 3000) {
            return 'ultra';
        }

        if ($longEdge >= 1920) {
            return 'high';
        }

        return 'standard';
    }

    private function orientationValue(Photo $photo): string
    {
        if ($photo->width === null || $photo->height === null) {
            return 'unknown';
        }

        if ($photo->width > $photo->height) {
            return 'landscape';
        }

        if ($photo->height > $photo->width) {
            return 'portrait';
        }

        return 'square';
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