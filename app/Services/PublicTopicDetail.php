<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicTopicDetail
{
    public function __construct(private readonly HomepageSettings $settings, private readonly PublicSeo $seo) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request, string $slug): array
    {
        $settings = $this->settings->formState();
        $topic = $this->topic($settings, $slug);

        if ($topic === null) {
            abort(404);
        }

        $topicHeader = $this->topicHeader($topic, $slug);

        return [
            'site' => $this->site($settings),
            'navigation' => $this->navigation($settings),
            'seo' => $this->seo->topic($topicHeader),
            'topic' => $topicHeader,
            'albums' => [
                'data' => $this->albums(Arr::wrap($topic['album_ids'] ?? [])),
            ],
            'photos' => [
                'data' => $this->photos(Arr::wrap($topic['photo_ids'] ?? []), $slug),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed> | null
     */
    private function topic(array $settings, string $slug): ?array
    {
        $url = '/topics/'.$slug;

        return collect(Arr::get($settings, 'topic_module.items', []))
            ->first(function (array $item) use ($url): bool {
                return (bool) ($item['enabled'] ?? true)
                    && filled($item['title'] ?? null)
                    && trim((string) ($item['url'] ?? '')) === $url;
            });
    }

    /**
     * @param  array<string, mixed>  $topic
     * @return array<string, mixed>
     */
    private function topicHeader(array $topic, string $slug): array
    {
        return [
            'title' => trim((string) ($topic['title'] ?? '')),
            'slug' => $slug,
            'url' => '/topics/'.$slug,
            'description' => filled($topic['description'] ?? null) ? (string) $topic['description'] : null,
            'cover_image_url' => $this->mediaUrl($topic['cover_image_path'] ?? null),
            'cover_alt' => trim((string) ($topic['title'] ?? '专题')),
        ];
    }

    /**
     * @param  array<int, mixed>  $albumIds
     * @return array<int, array<string, mixed>>
     */
    private function albums(array $albumIds): array
    {
        $orderedIds = $this->orderedIds($albumIds);

        if ($orderedIds === []) {
            return [];
        }

        $albums = Album::query()
            ->published()
            ->whereIn('id', $orderedIds)
            ->whereHas('photos', fn (Builder $query): Builder => $this->publicPhotoConstraint($query))
            ->withCount([
                'photos as public_photos_count' => fn (Builder $query): Builder => $this->publicPhotoConstraint($query),
            ])
            ->with([
                'categories.parent',
                'photos' => fn ($query) => $this->publicPhotoConstraint($query)
                    ->orderByDesc('photos.published_at')
                    ->orderByDesc('photos.id'),
            ])
            ->get()
            ->keyBy('id');

        return collect($orderedIds)
            ->map(fn (int $id) => $albums->get($id))
            ->filter(fn ($album): bool => $album instanceof Album)
            ->map(fn (Album $album): array => $this->albumCard($album))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $photoIds
     * @return array<int, array<string, mixed>>
     */
    private function photos(array $photoIds, string $slug): array
    {
        $orderedIds = $this->orderedIds($photoIds);

        if ($orderedIds === []) {
            return [];
        }

        $photos = Photo::query()
            ->published()
            ->whereNotIn('copyright_status', ['restricted', 'remove_requested'])
            ->whereIn('id', $orderedIds)
            ->with(['categories.parent', 'tags'])
            ->get()
            ->keyBy('id');

        return collect($orderedIds)
            ->map(fn (int $id) => $photos->get($id))
            ->filter(fn ($photo): bool => $photo instanceof Photo)
            ->map(fn (Photo $photo): array => $this->photoCard($photo, $slug))
            ->values()
            ->all();
    }

    private function publicPhotoConstraint($query)
    {
        return $query
            ->where('photos.status', 'published')
            ->whereNotIn('photos.copyright_status', ['restricted', 'remove_requested']);
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function orderedIds(array $ids): array
    {
        return collect($ids)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function albumCard(Album $album): array
    {
        $categories = $this->categories($album->categories);
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
    private function photoCard(Photo $photo, string $slug): array
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
            'url' => '/photos/'.$photo->uuid.'?topic='.rawurlencode($slug),
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

    private function mediaUrl(mixed $path): ?string
    {
        return PublicMediaUrl::fromPublicDisk($path);
    }
}
