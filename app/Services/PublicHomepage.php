<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicHomepage
{
    public function __construct(private readonly HomepageSettings $settings, private readonly PublicSeo $seo) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $settings = $this->settings->formState();

        return [
            'site' => $this->site($settings),
            'navigation' => $this->navigation($settings),
            'seo' => $this->seo->home(),
            'hero_slides' => $this->heroSlides($settings),
            'category_module' => $this->categoryModule($settings),
            'latest_photos' => $this->latestPhotos($settings),
            'topic_module' => $this->topicModule($settings),
            'footer' => $this->footer($settings),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shell(): array
    {
        return $this->shellFromSettings($this->settings->formState());
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function shellFromSettings(array $settings): array
    {
        return [
            'site' => $this->site($settings),
            'navigation' => $this->navigation($settings),
            'footer' => $this->footer($settings),
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
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function heroSlides(array $settings): array
    {
        $slides = collect(Arr::get($settings, 'hero_slides', []))
            ->filter(fn (array $slide): bool => (bool) ($slide['enabled'] ?? true))
            ->take(5)
            ->map(fn (array $slide): array => [
                'title' => $slide['title'] ?? '梅西影像档案库',
                'subtitle' => $slide['subtitle'] ?? '按时间、赛事、相册和标签整理梅西图片资料',
                'button_label' => $slide['button_label'] ?? null,
                'button_url' => $slide['button_url'] ?? null,
                'desktop_image_url' => $this->mediaUrl($slide['desktop_image_path'] ?? null),
                'mobile_image_url' => $this->mediaUrl($slide['mobile_image_path'] ?? null),
            ])
            ->values()
            ->all();

        if ($slides !== []) {
            return $slides;
        }

        return [[
            'title' => '梅西影像档案库',
            'subtitle' => '按时间、赛事、球队、相册、标签和来源沉淀中文影像资料',
            'button_label' => '浏览图库',
            'button_url' => '/photos',
            'desktop_image_url' => null,
            'mobile_image_url' => null,
        ]];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function categoryModule(array $settings): array
    {
        $module = Arr::get($settings, 'category_module', []);
        $displayCount = $this->intBetween($module['display_count'] ?? 7, 1, 12);
        $configuredTabs = collect($module['tabs'] ?? [])
            ->filter(fn (array $tab): bool => (bool) ($tab['enabled'] ?? true));

        $tabs = $configuredTabs->isNotEmpty()
            ? $configuredTabs->take(7)->map(fn (array $tab): array => $this->categoryTab($tab, $displayCount))->values()
            : $this->defaultCategoryTabs($displayCount);

        return [
            'enabled' => (bool) ($module['enabled'] ?? true),
            'title' => $module['title'] ?? '分类浏览',
            'more_url' => $module['more_url'] ?? '/albums',
            'tabs' => $tabs->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function defaultCategoryTabs(int $displayCount)
    {
        return Category::query()
            ->roots()
            ->where('visibility', 'public')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->take(7)
            ->get()
            ->map(fn (Category $category): array => $this->categoryTab([
                'category_id' => $category->id,
                'label' => $category->name,
            ], $displayCount));
    }

    /**
     * @param  array<string, mixed>  $tab
     * @return array<string, mixed>
     */
    private function categoryTab(array $tab, int $displayCount): array
    {
        $category = filled($tab['category_id'] ?? null)
            ? Category::query()->find((int) $tab['category_id'])
            : null;
        $configuredItems = $this->configuredCategoryItems($tab);
        $configuredPhotoIds = collect($configuredItems)
            ->pluck('id')
            ->filter(fn (string $id): bool => Str::startsWith($id, 'photo:'))
            ->map(fn (string $id): int => (int) Str::after($id, 'photo:'))
            ->values()
            ->all();
        $needed = max(0, $displayCount - count($configuredItems));

        return [
            'label' => filled($tab['label'] ?? null) ? $tab['label'] : ($category?->name ?? '分类'),
            'category_id' => $category?->id,
            'items' => collect($configuredItems)
                ->concat($this->autoCategoryItems($category, $needed, $configuredPhotoIds))
                ->take($displayCount)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $tab
     * @return array<int, array<string, mixed>>
     */
    private function configuredCategoryItems(array $tab): array
    {
        $photoIds = collect(Arr::wrap($tab['photo_ids'] ?? []))->filter()->map(fn (mixed $id): int => (int) $id)->values();
        $albumIds = collect(Arr::wrap($tab['album_ids'] ?? []))->filter()->map(fn (mixed $id): int => (int) $id)->values();

        $photos = $this->publicPhotosQuery()
            ->whereIn('id', $photoIds)
            ->get()
            ->sortBy(fn (Photo $photo): int => $photoIds->search($photo->id))
            ->map(fn (Photo $photo): array => $this->photoCard($photo));

        $albums = Album::query()
            ->published()
            ->whereIn('id', $albumIds)
            ->with('photos')
            ->get()
            ->sortBy(fn (Album $album): int => $albumIds->search($album->id))
            ->map(fn (Album $album): array => $this->albumCard($album));

        return $photos->concat($albums)->values()->all();
    }

    /**
     * @param  array<int, int>  $excludePhotoIds
     * @return array<int, array<string, mixed>>
     */
    private function autoCategoryItems(?Category $category, int $limit, array $excludePhotoIds): array
    {
        if ($limit <= 0 || $category === null) {
            return [];
        }

        return $this->publicPhotosQuery()
            ->whereNotIn('id', $excludePhotoIds)
            ->whereHas('categories', fn (Builder $query): Builder => $query->where('parent_id', $category->id))
            ->limit($limit)
            ->get()
            ->map(fn (Photo $photo): array => $this->photoCard($photo))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function latestPhotos(array $settings): array
    {
        $module = Arr::get($settings, 'latest_photos', []);
        $displayCount = $this->intBetween($module['display_count'] ?? 15, 3, 30);
        $pinnedIds = collect(Arr::wrap($module['pinned_photo_ids'] ?? []))
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $pinned = $this->publicPhotosQuery()
            ->whereIn('id', $pinnedIds)
            ->get()
            ->sortBy(fn (Photo $photo): int => $pinnedIds->search($photo->id))
            ->values();

        $remaining = $this->publicPhotosQuery()
            ->whereNotIn('id', $pinned->pluck('id'))
            ->limit(max(0, $displayCount - $pinned->count()))
            ->get();

        return [
            'enabled' => (bool) ($module['enabled'] ?? true),
            'title' => $module['title'] ?? '最新照片',
            'display_count' => $displayCount,
            'items' => $pinned
                ->concat($remaining)
                ->take($displayCount)
                ->map(fn (Photo $photo): array => $this->photoCard($photo))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function topicModule(array $settings): array
    {
        $module = Arr::get($settings, 'topic_module', []);
        $items = collect($module['items'] ?? [])
            ->filter(fn (array $item): bool => (bool) ($item['enabled'] ?? true))
            ->take($this->intBetween($module['display_count'] ?? 6, 1, 12))
            ->map(fn (array $item): array => [
                'title' => (string) ($item['title'] ?? ''),
                'url' => (string) ($item['url'] ?? '#'),
                'cover_image_url' => $this->mediaUrl($item['cover_image_path'] ?? null),
            ])
            ->filter(fn (array $item): bool => filled($item['title']))
            ->values()
            ->all();

        return [
            'enabled' => (bool) ($module['enabled'] ?? true),
            'title' => $module['title'] ?? '专题',
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function footer(array $settings): array
    {
        $footer = Arr::get($settings, 'footer', []);

        return [
            'copyright_text' => $footer['copyright_text'] ?? '梅西影像档案库',
            'icp_text' => $footer['icp_text'] ?? null,
            'links' => $this->withStaticPageLinks($this->enabledLinks($footer['links'] ?? [])),
            'social_links' => $this->enabledLinks($footer['social_links'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{label: string, url: string}>
     */
    private function enabledLinks(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item): bool => (bool) ($item['enabled'] ?? false))
            ->map(fn (array $item): array => [
                'label' => (string) ($item['label'] ?? $item['platform'] ?? ''),
                'url' => (string) ($item['url'] ?? '#'),
            ])
            ->filter(fn (array $item): bool => filled($item['label']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{label: string, url: string}>  $links
     * @return array<int, array{label: string, url: string}>
     */
    private function withStaticPageLinks(array $links): array
    {
        $existingUrls = collect($links)->pluck('url')->all();

        return collect($links)
            ->concat(collect($this->staticPageLinks())->reject(fn (array $link): bool => in_array($link['url'], $existingUrls, true)))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function staticPageLinks(): array
    {
        return [
            ['label' => '关于本站', 'url' => '/about'],
            ['label' => '版权说明', 'url' => '/copyright'],
            ['label' => '下架申请', 'url' => '/takedown'],
            ['label' => '隐私政策', 'url' => '/privacy'],
            ['label' => '用户协议', 'url' => '/terms'],
        ];
    }

    private function publicPhotosQuery(): Builder
    {
        return Photo::query()
            ->published()
            ->whereNotIn('copyright_status', ['restricted', 'remove_requested'])
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function photoCard(Photo $photo): array
    {
        return [
            'id' => 'photo:'.$photo->id,
            'type' => 'photo',
            'title' => $photo->title,
            'url' => '/photos/'.$photo->uuid,
            'image_url' => $this->mediaUrl($photo->thumbnail_key ?: $photo->display_key),
            'alt' => $photo->title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function albumCard(Album $album): array
    {
        $coverPhoto = $album->photos
            ->filter(fn (Photo $photo): bool => $photo->status === 'published' && ! in_array($photo->copyright_status, ['restricted', 'remove_requested'], true))
            ->firstWhere('id', $album->cover_photo_id)
            ?? $album->photos->first(fn (Photo $photo): bool => $photo->status === 'published' && ! in_array($photo->copyright_status, ['restricted', 'remove_requested'], true));

        return [
            'id' => 'album:'.$album->id,
            'type' => 'album',
            'title' => $album->title,
            'url' => '/albums/'.$album->slug,
            'image_url' => $coverPhoto instanceof Photo ? $this->mediaUrl($coverPhoto->thumbnail_key ?: $coverPhoto->display_key) : null,
            'alt' => $album->title,
        ];
    }

    private function mediaUrl(mixed $path): ?string
    {
        return PublicMediaUrl::fromPublicDisk($path);
    }

    private function intBetween(mixed $value, int $min, int $max): int
    {
        return min($max, max($min, (int) $value));
    }
}
