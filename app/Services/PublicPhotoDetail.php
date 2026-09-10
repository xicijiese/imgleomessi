<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PublicPhotoDetail
{
    private const RELATED_LIMIT = 8;

    public function __construct(private readonly HomepageSettings $settings, private readonly PublicSeo $seo) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request, string $uuid): array
    {
        $settings = $this->settings->formState();
        $photo = $this->photo($uuid);
        $context = $this->context($request, $photo, $settings);
        $photoDetail = $this->photoDetail($photo, $request);

        return [
            'site' => $this->site($settings),
            'navigation' => $this->navigation($settings),
            'seo' => $this->seo->photo($photoDetail),
            'photo' => $photoDetail,
            'context' => array_merge($context['summary'], ['type' => $context['type']]),
            'adjacent' => $this->adjacent($photo, $context),
            'related' => [
                'data' => $this->related($photo, $context),
            ],
            'comments' => $this->comments($photo, $request),
        ];
    }

    private function photo(string $uuid): Photo
    {
        return $this->publicPhotosQuery()
            ->where('uuid', $uuid)
            ->with([
                'albums' => fn ($query) => $query
                    ->where('albums.status', 'published')
                    ->orderBy('albums.sort_order')
                    ->orderBy('albums.id'),
                'categories.parent',
                'tags',
            ])
            ->firstOrFail();
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

    /**
     * @param  array<string, mixed>  $settings
     * @return array{summary: array<string, mixed>, type: string, ordered_photo_ids: array<int, int>}
     */
    private function context(Request $request, Photo $photo, array $settings): array
    {
        $albumContext = $this->albumContext($request, $photo);

        if ($albumContext !== null) {
            return $albumContext;
        }

        $topicContext = $this->topicContext($request, $photo, $settings);

        if ($topicContext !== null) {
            return $topicContext;
        }

        return [
            'type' => 'gallery',
            'ordered_photo_ids' => [],
            'summary' => [
                'title' => '图库',
                'slug' => null,
                'return_url' => '/photos',
            ],
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, type: string, ordered_photo_ids: array<int, int>} | null
     */
    private function albumContext(Request $request, Photo $photo): ?array
    {
        $slug = $request->query('album');

        if (! is_string($slug) || blank($slug)) {
            return null;
        }

        $album = Album::query()
            ->published()
            ->where('slug', $slug)
            ->whereHas('photos', fn (Builder $query): Builder => $query->where('photos.id', $photo->id))
            ->whereHas('photos', fn (Builder $query): Builder => $this->publicPhotoConstraint($query))
            ->first();

        if (! $album instanceof Album) {
            return null;
        }

        $orderedPhotoIds = $album->photos()
            ->where(fn ($query) => $this->publicPhotoConstraint($query))
            ->orderByDesc('photos.event_date')
            ->orderByDesc('photos.published_at')
            ->orderByDesc('photos.id')
            ->pluck('photos.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return [
            'type' => 'album',
            'ordered_photo_ids' => $orderedPhotoIds,
            'summary' => [
                'title' => $album->title,
                'slug' => $album->slug,
                'return_url' => '/albums/'.$album->slug,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{summary: array<string, mixed>, type: string, ordered_photo_ids: array<int, int>} | null
     */
    private function topicContext(Request $request, Photo $photo, array $settings): ?array
    {
        $slug = $request->query('topic');

        if (! is_string($slug) || blank($slug)) {
            return null;
        }

        $url = '/topics/'.$slug;
        $topic = collect(Arr::get($settings, 'topic_module.items', []))
            ->first(function (array $item) use ($url, $photo): bool {
                $photoIds = collect(Arr::wrap($item['photo_ids'] ?? []))
                    ->map(fn (mixed $id): int => (int) $id);

                return (bool) ($item['enabled'] ?? true)
                    && filled($item['title'] ?? null)
                    && trim((string) ($item['url'] ?? '')) === $url
                    && $photoIds->contains($photo->id);
            });

        if (! is_array($topic)) {
            return null;
        }

        $configuredIds = $this->orderedIds(Arr::wrap($topic['photo_ids'] ?? []));
        $publicIds = $this->publicPhotosQuery()
            ->whereIn('id', $configuredIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $orderedPhotoIds = collect($configuredIds)
            ->filter(fn (int $id): bool => in_array($id, $publicIds, true))
            ->values()
            ->all();

        return [
            'type' => 'topic',
            'ordered_photo_ids' => $orderedPhotoIds,
            'summary' => [
                'title' => trim((string) $topic['title']),
                'slug' => $slug,
                'return_url' => '/topics/'.$slug,
            ],
        ];
    }

    /**
     * @param  array{summary: array<string, mixed>, type: string, ordered_photo_ids: array<int, int>}  $context
     * @return array{previous: array<string, mixed> | null, next: array<string, mixed> | null}
     */
    private function adjacent(Photo $photo, array $context): array
    {
        $orderedIds = $context['ordered_photo_ids'];
        $index = array_search($photo->id, $orderedIds, true);

        if ($index === false) {
            return ['previous' => null, 'next' => null];
        }

        return [
            'previous' => $this->adjacentCard($orderedIds[$index - 1] ?? null, $context),
            'next' => $this->adjacentCard($orderedIds[$index + 1] ?? null, $context),
        ];
    }

    /**
     * @param  array{summary: array<string, mixed>, type: string, ordered_photo_ids: array<int, int>}  $context
     * @return array<string, mixed> | null
     */
    private function adjacentCard(?int $photoId, array $context): ?array
    {
        if ($photoId === null) {
            return null;
        }

        $photo = $this->publicPhotosQuery()->where('id', $photoId)->first();

        return $photo instanceof Photo ? $this->photoCard($photo, $context) : null;
    }

    /**
     * @param  array{summary: array<string, mixed>, type: string, ordered_photo_ids: array<int, int>}  $context
     * @return array<int, array<string, mixed>>
     */
    private function related(Photo $photo, array $context): array
    {
        if (in_array($context['type'], ['album', 'topic'], true) && $context['ordered_photo_ids'] !== []) {
            $ids = collect($context['ordered_photo_ids'])
                ->reject(fn (int $id): bool => $id === $photo->id)
                ->take(self::RELATED_LIMIT)
                ->values()
                ->all();

            return $this->orderedPhotoCards($ids, $context);
        }

        $categoryIds = $photo->categories->pluck('id')->all();
        $tagIds = $photo->tags->pluck('id')->all();

        $related = $this->publicPhotosQuery()
            ->where('id', '!=', $photo->id)
            ->where(function (Builder $query) use ($categoryIds, $tagIds): void {
                if ($categoryIds !== []) {
                    $query->whereHas('categories', fn (Builder $query): Builder => $query->whereIn('categories.id', $categoryIds));
                }

                if ($tagIds !== []) {
                    $query->orWhereHas('tags', fn (Builder $query): Builder => $query->whereIn('tags.id', $tagIds));
                }
            })
            ->latest('published_at')
            ->latest('id')
            ->limit(self::RELATED_LIMIT)
            ->get();

        return $related
            ->map(fn (Photo $photo): array => $this->photoCard($photo, $context))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @param  array{summary: array<string, mixed>, type: string, ordered_photo_ids: array<int, int>}  $context
     * @return array<int, array<string, mixed>>
     */
    private function orderedPhotoCards(array $ids, array $context): array
    {
        if ($ids === []) {
            return [];
        }

        $photos = $this->publicPhotosQuery()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $photos->get($id))
            ->filter(fn ($photo): bool => $photo instanceof Photo)
            ->map(fn (Photo $photo): array => $this->photoCard($photo, $context))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function photoDetail(Photo $photo, Request $request): array
    {
        $categories = $this->categories($photo->categories);
        $tags = $this->tags($photo->tags);

        return [
            'id' => $photo->id,
            'uuid' => $photo->uuid,
            'title' => $photo->title,
            'description' => $photo->description,
            'url' => '/photos/'.$photo->uuid,
            'image_url' => $this->mediaUrl($photo->display_key ?: $photo->thumbnail_key),
            'alt' => filled($photo->title) ? $photo->title : '梅西图片档案',
            'taken_at' => $photo->taken_at?->format('Y-m-d H:i'),
            'event_date' => $photo->event_date?->toDateString(),
            'published_at' => $photo->published_at?->toDateString(),
            'copyright_status' => [
                'value' => $photo->copyright_status,
                'label' => Photo::COPYRIGHT_STATUSES[$photo->copyright_status] ?? '待确认',
            ],
            'width' => $photo->width,
            'height' => $photo->height,
            'mime_type' => $photo->mime_type,
            'file_size' => $photo->file_size,
            'file_size_label' => $this->fileSizeLabel($photo->file_size),
            'categories' => $categories->all(),
            'tags' => $tags->all(),
            'albums' => $this->albums($photo),
            'source' => $this->source($photo),
            'interactions' => $this->interactions($photo, $request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function interactions(Photo $photo, Request $request): array
    {
        $user = $request->user();
        $isBanned = $user?->isBanned() ?? false;

        return [
            'can_interact' => $user !== null && ! $isBanned,
            'is_blocked' => $isBanned,
            'blocked_reason' => $isBanned ? '账号已被封禁，暂不能提交互动内容。' : null,
            'is_favorited' => $user !== null
                ? $photo->favorites()->where('user_id', $user->id)->exists()
                : false,
            'is_liked' => $user !== null
                ? $photo->likes()->where('user_id', $user->id)->exists()
                : false,
            'likes_count' => $photo->likes()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function comments(Photo $photo, Request $request): array
    {
        $user = $request->user();
        $isBanned = $user?->isBanned() ?? false;
        $comments = $photo->comments()
            ->published()
            ->discussion()
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->limit(20)
            ->get();

        return [
            'can_submit' => $user !== null && ! $isBanned,
            'can_report' => $user !== null && ! $isBanned,
            'is_blocked' => $isBanned,
            'blocked_reason' => $isBanned ? '账号已被封禁，暂不能提交互动内容。' : null,
            'total' => $photo->comments()->published()->discussion()->count(),
            'data' => $comments
                ->map(fn (Comment $comment): array => [
                    'id' => $comment->id,
                    'user_name' => $comment->user?->name ?? '用户',
                    'content' => $comment->content,
                    'created_at' => $comment->created_at?->format('Y-m-d H:i'),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{summary: array<string, mixed>, type: string, ordered_photo_ids: array<int, int>}  $context
     * @return array<string, mixed>
     */
    private function photoCard(Photo $photo, array $context): array
    {
        return [
            'id' => $photo->id,
            'uuid' => $photo->uuid,
            'title' => $photo->title,
            'url' => $this->photoUrl($photo, $context),
            'image_url' => $this->mediaUrl($photo->display_key ?: $photo->thumbnail_key),
            'alt' => filled($photo->title) ? $photo->title : '梅西图片档案',
            'event_date' => $photo->event_date?->toDateString(),
            'published_at' => $photo->published_at?->toDateString(),
        ];
    }

    /**
     * @param  array{summary: array<string, mixed>, type: string, ordered_photo_ids: array<int, int>}  $context
     */
    private function photoUrl(Photo $photo, array $context): string
    {
        $url = '/photos/'.$photo->uuid;
        $slug = $context['summary']['slug'] ?? null;

        if ($context['type'] === 'album' && is_string($slug)) {
            return $url.'?album='.rawurlencode($slug);
        }

        if ($context['type'] === 'topic' && is_string($slug)) {
            return $url.'?topic='.rawurlencode($slug);
        }

        return $url;
    }

    private function source(Photo $photo): ?array
    {
        return filled($photo->source_url) ? [
            'source_url' => $photo->source_url,
        ] : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function albums(Photo $photo): array
    {
        return $photo->albums
            ->map(fn (Album $album): array => [
                'id' => $album->id,
                'title' => $album->title,
                'slug' => $album->slug,
                'url' => '/albums/'.$album->slug,
            ])
            ->values()
            ->all();
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
                'url' => $category->parent?->slug ? '/photos?categories['.$category->parent->slug.']='.$category->id : '/photos',
            ])
            ->values();
    }

    private function tags($tags)
    {
        return $tags
            ->sortBy(fn (Tag $tag): string => $tag->sort_order.'-'.$tag->id)
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'url' => '/photos?tags[]='.$tag->id,
            ])
            ->values();
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

    private function fileSizeLabel(?int $bytes): ?string
    {
        if ($bytes === null || $bytes <= 0) {
            return null;
        }

        if ($bytes >= 1024 * 1024) {
            return (string) round($bytes / 1024 / 1024).' MB';
        }

        if ($bytes >= 1024) {
            return (string) round($bytes / 1024).' KB';
        }

        return $bytes.' B';
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
