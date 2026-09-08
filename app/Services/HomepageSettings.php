<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HomepageSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'site' => [
                'name' => '梅西影像档案库',
                'logo_path' => null,
                'search_placeholder' => '搜索图片、相册、赛事或年份',
                'copyright_text' => '梅西影像档案库',
                'icp_text' => null,
                'contact_email' => null,
            ],
            'navigation' => [
                'items' => [
                    ['label' => '首页', 'url' => '/', 'enabled' => true],
                    ['label' => '图库', 'url' => '/photos', 'enabled' => true],
                    ['label' => '相册', 'url' => '/albums', 'enabled' => true],
                    ['label' => '专题', 'url' => '/topics', 'enabled' => true],
                    ['label' => '时间线', 'url' => '/timeline', 'enabled' => true],
                    ['label' => '排行榜', 'url' => '/rankings', 'enabled' => true],
                    ['label' => '支持本站', 'url' => '/support', 'enabled' => true],
                    ['label' => '个人中心', 'url' => '/me', 'enabled' => false],
                ],
            ],
            'hero_slides' => [],
            'category_module' => [
                'enabled' => true,
                'title' => '分类浏览',
                'display_count' => 7,
                'more_url' => '/albums',
                'tabs' => [],
            ],
            'latest_photos' => [
                'enabled' => true,
                'title' => '最新照片',
                'display_count' => 15,
                'sort' => 'published_at_desc',
                'pinned_photo_ids' => [],
                'click_target' => 'photo_detail',
            ],
            'topic_module' => [
                'enabled' => true,
                'title' => '专题',
                'display_count' => 6,
                'items' => [],
            ],
            'footer' => [
                'copyright_text' => '梅西影像档案库',
                'icp_text' => null,
                'links' => [
                    ['label' => '关于本站', 'url' => '/about', 'enabled' => true],
                    ['label' => '版权说明', 'url' => '/copyright', 'enabled' => true],
                    ['label' => '下架申请', 'url' => '/takedown', 'enabled' => true],
                    ['label' => '隐私政策', 'url' => '/privacy', 'enabled' => true],
                    ['label' => '用户协议', 'url' => '/terms', 'enabled' => true],
                ],
                'social_links' => [],
            ],
            'internal_note' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formState(): array
    {
        $state = self::defaults();

        foreach ($this->settingMap() as $path => [$group, $key]) {
            $value = Setting::value($group, $key);

            if ($value !== null) {
                Arr::set($state, $path, $value);
            }
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function save(array $state, ?User $user = null): void
    {
        $state = array_replace_recursive(self::defaults(), $state);

        $this->validatePublicContent($state);

        foreach ($this->settingMap() as $path => [$group, $key, $description]) {
            Setting::setValue($group, $key, Arr::get($state, $path), $user, $description);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function validatePublicContent(array $state): void
    {
        $photoIds = collect(Arr::get($state, 'latest_photos.pinned_photo_ids', []))
            ->merge($this->collectNestedIds(Arr::get($state, 'category_module.tabs', []), 'photo_ids'))
            ->merge($this->collectNestedIds(Arr::get($state, 'topic_module.items', []), 'photo_ids'))
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($photoIds->isNotEmpty()) {
            $allowedPhotoIds = Photo::query()
                ->published()
                ->whereNotIn('copyright_status', ['restricted', 'remove_requested'])
                ->whereIn('id', $photoIds)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id);

            if ($allowedPhotoIds->diff($photoIds)->isNotEmpty() || $photoIds->diff($allowedPhotoIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'data.latest_photos.pinned_photo_ids' => '首页公开模块只能选择已发布且可公开展示的图片。',
                ]);
            }
        }

        $albumIds = $this->collectNestedIds(Arr::get($state, 'category_module.tabs', []), 'album_ids')
            ->merge($this->collectNestedIds(Arr::get($state, 'topic_module.items', []), 'album_ids'))
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($albumIds->isNotEmpty()) {
            $allowedAlbumIds = Album::query()
                ->published()
                ->whereHas('photos', fn ($query) => $query
                    ->where('photos.status', 'published')
                    ->whereNotIn('photos.copyright_status', ['restricted', 'remove_requested']))
                ->whereIn('id', $albumIds)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id);

            if ($allowedAlbumIds->diff($albumIds)->isNotEmpty() || $albumIds->diff($allowedAlbumIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'data.category_module.tabs' => '公开模块只能选择已发布且包含公开图片的相册。',
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, mixed>
     */
    private function collectNestedIds(array $items, string $key): Collection
    {
        return collect($items)->flatMap(fn (array $item): array => Arr::wrap($item[$key] ?? []));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    private function settingMap(): array
    {
        return [
            'site' => ['site', 'basic', '站点基础信息'],
            'navigation' => ['site', 'navigation', '首页导航配置'],
            'hero_slides' => ['home', 'hero_slides', '首页头图轮播'],
            'category_module' => ['home', 'category_module', '首页分类模块'],
            'latest_photos' => ['home', 'latest_photos', '首页最新照片模块'],
            'topic_module' => ['home', 'topic_module', '首页专题模块'],
            'footer' => ['site', 'footer', '页脚配置'],
            'internal_note' => ['home', 'internal_note', '首页配置内部备注'],
        ];
    }
}
