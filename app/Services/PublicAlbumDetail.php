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

class PublicAlbumDetail
{
    private const PER_PAGE = 24;

    private const SORTS = [
        'event_desc' => '事件日期新到旧',
        'event_asc' => '事件日期旧到新',
        'published_desc' => '最新发布',
        'published_asc' => '最早发布',
    ];

    public function __construct(private readonly HomepageSettings $settings, private readonly PublicSeo $seo) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request, string $slug): array
    {
        $sort = $this->sortValue($request->query('sort'));
        $album = $this->publishedAlbum($slug);
        $coverPhoto = $this->coverPhoto($album);
        $query = $album->photos()
            ->where(fn ($query) => $this->publicPhotoConstraint($query))
            ->with(['categories.parent', 'tags']);
        $this->applySort($query, $sort);

        /** @var LengthAwarePaginator<int, Photo> $paginator */
        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();
        $albumHeader = $this->albumHeader($album, $coverPhoto);

        return [
            'site' => $this->site(),
            'navigation' => $this->navigation(),
            'seo' => $this->seo->album($albumHeader),
            'filters' => [
                'sort' => $sort,
            ],
            'filter_options' => [
                'sorts' => collect(self::SORTS)
                    ->map(fn (string $label, string $value): array => [
                        'value' => $value,
                        'label' => $label,
                    ])
                    ->values()
                    ->all(),
            ],
            'album' => $albumHeader,
            'photos' => [
                'data' => $paginator->getCollection()
                    ->map(fn (Photo $photo): array => $this->photoCard($photo, $album))
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

    private function publishedAlbum(string $slug): Album
    {
        $album = Album::query()
            ->published()
            ->where('slug', $slug)
            ->with('categories.parent')
            ->withCount([
                'photos as public_photos_count' => fn (Builder $query): Builder => $this->publicPhotoConstraint($query),
            ])
            ->firstOrFail();

        if ((int) $album->public_photos_count < 1) {
            abort(404);
        }

        return $album;
    }

    private function coverPhoto(Album $album): ?Photo
    {
        if ($album->cover_photo_id !== null) {
            $coverPhoto = $album->photos()
                ->where('photos.id', $album->cover_photo_id)
                ->where(fn ($query) => $this->publicPhotoConstraint($query))
                ->first();

            if ($coverPhoto instanceof Photo) {
                return $coverPhoto;
            }
        }

        return $album->photos()
            ->where(fn ($query) => $this->publicPhotoConstraint($query))
            ->orderByDesc('photos.published_at')
            ->orderByDesc('photos.id')
            ->first();
    }

    private function publicPhotoConstraint($query)
    {
        return $query
            ->where('photos.status', 'published')
            ->whereNotIn('photos.copyright_status', ['restricted', 'remove_requested']);
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'event_asc' => $query->orderBy('photos.event_date')->orderBy('photos.published_at')->orderBy('photos.id'),
            'published_desc' => $query->orderByDesc('photos.published_at')->orderByDesc('photos.id'),
            'published_asc' => $query->orderBy('photos.published_at')->orderBy('photos.id'),
            default => $query->orderByDesc('photos.event_date')->orderByDesc('photos.published_at')->orderByDesc('photos.id'),
        };
    }

    private function sortValue(mixed $value): string
    {
        if (is_string($value) && array_key_exists($value, self::SORTS)) {
            return $value;
        }

        return 'event_desc';
    }

    /**
     * @return array<string, mixed>
     */
    private function albumHeader(Album $album, ?Photo $coverPhoto): array
    {
        $categories = $this->categories($album->categories);

        return [
            'id' => $album->id,
            'title' => $album->title,
            'slug' => $album->slug,
            'description' => $album->description,
            'url' => '/albums/'.$album->slug,
            'cover_image_url' => $coverPhoto instanceof Photo ? $this->mediaUrl($coverPhoto->display_key ?: $coverPhoto->thumbnail_key) : null,
            'cover_alt' => $album->title,
            'published_at' => $album->published_at?->toDateString(),
            'public_photos_count' => (int) ($album->public_photos_count ?? 0),
            'category_summary' => $categories->pluck('name')->take(4)->implode(' / '),
            'categories' => $categories->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photoCard(Photo $photo, Album $album): array
    {
        $categories = $this->categories($photo->categories);
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
            'url' => '/photos/'.$photo->uuid.'?album='.rawurlencode($album->slug),
            'image_url' => $this->mediaUrl($photo->display_key ?: $photo->thumbnail_key),
            'alt' => filled($photo->title) ? $photo->title : '梅西图片档案',
            'event_date' => $photo->event_date?->toDateString(),
            'published_at' => $photo->published_at?->toDateString(),
            'category_summary' => $categories->pluck('name')->take(3)->implode(' / '),
            'categories' => $categories->all(),
            'tags' => $tags->all(),
        ];
    }

    private function categories($categories)
    {
        return $categories
            ->filter(fn (Category $category): bool => $category->parent instanceof Category)
            ->sortBy(fn (Category $category): string => ($category->parent?->sort_order ?? 0).'-'.$category->sort_order.'-'.$category->id)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'root_name' => $category->parent?->name,
                'root_slug' => $category->parent?->slug,
            ])
            ->values();
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
