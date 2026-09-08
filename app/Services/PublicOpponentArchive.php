<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Category;
use App\Models\Opponent;
use App\Models\Photo;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicOpponentArchive
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
        $filters = ['q' => trim((string) $request->query('q', ''))];
        $opponents = $this->opponentCards($filters['q']);

        return [
            'site' => $this->site(),
            'navigation' => $this->navigation(),
            'seo' => $this->seo->opponentsIndex(),
            'dimension' => [
                'type' => 'opponents',
                'label' => '对手',
                'eyebrow' => 'Opponents',
                'title' => '对手',
                'subtitle' => '按比赛或活动中的对手浏览梅西公开影像，只聚合图片线索，不扩展为完整比赛资料库。',
                'path' => '/opponents',
                'empty_title' => '暂无可浏览的对手',
                'empty_description' => '只有启用且关联公开图片的对手，才会出现在这里。',
            ],
            'summary' => [
                'opponents_count' => count($opponents),
                'photos_count' => collect($opponents)->sum('public_photos_count'),
                'albums_count' => collect($opponents)->sum('public_albums_count'),
            ],
            'filters' => $filters,
            'opponents' => $opponents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showPayload(Request $request, string $slug): array
    {
        $opponent = $this->findPublicOpponent($slug);
        $filters = $this->detailFilters($request, $opponent);
        $query = $this->filteredPhotosQuery($opponent, $filters);
        $this->applySort($query, $filters['sort']);

        /** @var LengthAwarePaginator<int, Photo> $paginator */
        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();
        $albums = $this->albumCards($opponent);
        $years = $this->yearOptions($opponent);

        return [
            'site' => $this->site(),
            'navigation' => $this->navigation(),
            'seo' => $this->seo->opponentDetail($opponent->name, '/opponents/'.$opponent->slug),
            'dimension' => [
                'type' => 'opponents',
                'label' => '对手',
                'eyebrow' => 'Opponents',
                'index_url' => '/opponents',
                'gallery_url' => '/photos',
                'timeline_url' => $this->timelineUrl($years),
                'breadcrumbs' => [
                    ['label' => '对手', 'url' => '/opponents'],
                    ['label' => $opponent->name, 'url' => '/opponents/'.$opponent->slug],
                ],
            ],
            'opponent' => $this->opponentPayload($opponent),
            'summary' => [
                'photos_count' => $paginator->total(),
                'albums_count' => count($albums),
                'years_count' => count($years),
            ],
            'filters' => $filters,
            'filter_options' => [
                'years' => $years,
                'tags' => $this->tagOptions(false),
                'people_tags' => $this->tagOptions(true),
                'sorts' => $this->keyValueOptions(self::SORTS),
            ],
            'photos' => $this->paginatedPhotos($paginator),
            'albums' => $albums,
            'related_topics' => $this->relatedTopics(),
        ];
    }

    private function findPublicOpponent(string $slug): Opponent
    {
        return Opponent::query()
            ->active()
            ->where('slug', $slug)
            ->whereHas('photos', fn (Builder $query): Builder => $this->publicPhotoConstraint($query))
            ->firstOrFail();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function opponentCards(string $keyword): array
    {
        return Opponent::query()
            ->active()
            ->when(filled($keyword), function (Builder $query) use ($keyword): void {
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $keyword);
                $query->where(function (Builder $query) use ($escaped): void {
                    $query->where('name', 'like', "%{$escaped}%")
                        ->orWhere('country', 'like', "%{$escaped}%")
                        ->orWhere('aliases', 'like', "%{$escaped}%")
                        ->orWhere('description', 'like', "%{$escaped}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Opponent $opponent): array {
                $photosQuery = $this->publicPhotosForOpponentQuery($opponent);
                $photosCount = (clone $photosQuery)->count();

                if ($photosCount === 0) {
                    return [];
                }

                $cover = (clone $photosQuery)
                    ->orderByDesc('event_date')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->first(['id', 'title', 'display_key', 'thumbnail_key', 'event_date', 'published_at']);

                return [
                    'id' => $opponent->id,
                    'name' => $opponent->name,
                    'slug' => $opponent->slug,
                    'country' => $opponent->country,
                    'description' => Str::limit((string) $opponent->description, 120),
                    'url' => '/opponents/'.$opponent->slug,
                    'gallery_url' => '/photos',
                    'cover_image_url' => $cover instanceof Photo ? $this->mediaUrl($cover->display_key ?: $cover->thumbnail_key) : null,
                    'cover_alt' => $cover instanceof Photo ? $cover->title : $opponent->name,
                    'public_photos_count' => $photosCount,
                    'public_albums_count' => $this->publicAlbumsForOpponentQuery($opponent)->count(),
                    'latest_event_date' => $cover instanceof Photo ? $cover->event_date?->toDateString() : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function detailFilters(Request $request, Opponent $opponent): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'years' => $this->selectedYears($request, $opponent),
            'tags' => $this->selectedTags($request, false),
            'people_tags' => $this->selectedTags($request, true),
            'sort' => $this->sortValue($request->query('sort')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredPhotosQuery(Opponent $opponent, array $filters): Builder
    {
        $query = $this->publicPhotosForOpponentQuery($opponent)
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

        if ($filters['years'] !== []) {
            $query->where(function (Builder $query) use ($filters): void {
                foreach ($filters['years'] as $year) {
                    $query->orWhereBetween('event_date', [$year.'-01-01', $year.'-12-31']);
                }
            });
        }

        foreach ($filters['tags'] as $tagId) {
            $query->whereHas('tags', fn (Builder $query): Builder => $query->where('tags.id', $tagId));
        }

        foreach ($filters['people_tags'] as $tagId) {
            $query->whereHas('tags', fn (Builder $query): Builder => $query
                ->where('tags.id', $tagId)
                ->where('tags.type', '人物关系'));
        }

        return $query;
    }

    private function publicPhotosForOpponentQuery(Opponent $opponent): Builder
    {
        return Photo::query()
            ->published()
            ->whereNotIn('copyright_status', ['restricted', 'remove_requested'])
            ->whereHas('opponents', fn (Builder $query): Builder => $query->where('opponents.id', $opponent->id));
    }

    private function publicAlbumsForOpponentQuery(Opponent $opponent): Builder
    {
        return Album::query()
            ->published()
            ->whereHas('photos', fn (Builder $query): Builder => $this->publicPhotoConstraint($query)
                ->whereHas('opponents', fn (Builder $query): Builder => $query->where('opponents.id', $opponent->id)));
    }

    private function publicPhotoConstraint($query)
    {
        return $query
            ->where('photos.status', 'published')
            ->whereNotIn('photos.copyright_status', ['restricted', 'remove_requested']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function albumCards(Opponent $opponent): array
    {
        return $this->publicAlbumsForOpponentQuery($opponent)
            ->withCount([
                'photos as public_photos_count' => fn (Builder $query): Builder => $this->publicPhotoConstraint($query)
                    ->whereHas('opponents', fn (Builder $query): Builder => $query->where('opponents.id', $opponent->id)),
            ])
            ->with([
                'categories.parent',
                'photos' => fn ($query) => $this->publicPhotoConstraint($query)
                    ->whereHas('opponents', fn (Builder $query): Builder => $query->where('opponents.id', $opponent->id))
                    ->orderByDesc('photos.event_date')
                    ->orderByDesc('photos.published_at')
                    ->orderByDesc('photos.id'),
            ])
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
     * @return array<int, array{value: int, label: string}>
     */
    private function yearOptions(Opponent $opponent): array
    {
        return $this->publicPhotosForOpponentQuery($opponent)
            ->whereNotNull('event_date')
            ->orderByDesc('event_date')
            ->get(['event_date'])
            ->map(fn (Photo $photo): ?int => $photo->event_date?->year)
            ->filter()
            ->unique()
            ->values()
            ->map(fn (int $year): array => [
                'value' => $year,
                'label' => $year.' 年',
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function selectedYears(Request $request, Opponent $opponent): array
    {
        $available = collect($this->yearOptions($opponent))->pluck('value')->all();

        return collect(Arr::wrap($request->query('years', [])))
            ->filter(fn (mixed $year): bool => filled($year))
            ->map(fn (mixed $year): int => (int) $year)
            ->filter(fn (int $year): bool => in_array($year, $available, true))
            ->unique()
            ->values()
            ->all();
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
        } else {
            $query->where('type', '!=', '人物关系');
        }

        return $query
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
     * @return array<string, mixed>
     */
    private function opponentPayload(Opponent $opponent): array
    {
        return [
            'id' => $opponent->id,
            'name' => $opponent->name,
            'slug' => $opponent->slug,
            'country' => $opponent->country,
            'description' => $opponent->description,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, type: string}>
     */
    private function tagOptions(bool $peopleOnly): array
    {
        return Tag::query()
            ->when($peopleOnly, fn (Builder $query): Builder => $query->where('type', '人物关系'))
            ->when(! $peopleOnly, fn (Builder $query): Builder => $query->where('type', '!=', '人物关系'))
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
            ->all();
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
     * @param  array<int, array{value: int, label: string}>  $years
     */
    private function timelineUrl(array $years): string
    {
        if (count($years) === 1) {
            return '/timeline/'.$years[0]['value'];
        }

        return '/timeline';
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