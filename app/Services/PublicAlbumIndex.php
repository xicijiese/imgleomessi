<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicAlbumIndex
{
    private const PER_PAGE = 12;

    private const SORTS = [
        'default' => '默认排序',
        'published_desc' => '最新发布',
        'published_asc' => '最早发布',
        'updated_desc' => '最近更新',
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

        /** @var LengthAwarePaginator<int, Album> $paginator */
        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();

        return [
            'site' => $this->site(),
            'navigation' => $this->navigation(),
            'seo' => $this->seo->albumsIndex(),
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'albums' => [
                'data' => $paginator->getCollection()
                    ->map(fn (Album $album): array => $this->albumCard($album))
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
        return [
            'q' => trim((string) $request->query('q', '')),
            'categories' => $this->selectedCategories($request),
            'sort' => $this->sortValue($request->query('sort')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = Album::query()
            ->published()
            ->whereHas('photos', fn (Builder $query): Builder => $this->publicPhotoConstraint($query))
            ->withCount([
                'photos as public_photos_count' => fn (Builder $query): Builder => $this->publicPhotoConstraint($query),
            ])
            ->with([
                'categories.parent',
                'photos' => fn ($query) => $this->publicPhotoConstraint($query)
                    ->orderByDesc('photos.published_at')
                    ->orderByDesc('photos.id'),
            ]);

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

        return $query;
    }

    private function publicPhotoConstraint($query)
    {
        return $query
            ->where('photos.status', 'published')
            ->whereNotIn('photos.copyright_status', ['restricted', 'remove_requested']);
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'published_desc' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'published_asc' => $query->orderBy('published_at')->orderBy('id'),
            'updated_desc' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            default => $query->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('id'),
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

    private function sortValue(mixed $value): string
    {
        if (is_string($value) && array_key_exists($value, self::SORTS)) {
            return $value;
        }

        return 'default';
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
