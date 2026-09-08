<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicTopicIndex
{
    public function __construct(private readonly HomepageSettings $settings, private readonly PublicSeo $seo) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request): array
    {
        $settings = $this->settings->formState();

        return [
            'site' => $this->site($settings),
            'navigation' => $this->navigation($settings),
            'seo' => $this->seo->topicsIndex(),
            'topics' => [
                'data' => $this->topics($settings),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function topics(array $settings): array
    {
        return collect(Arr::get($settings, 'topic_module.items', []))
            ->filter(fn (array $item): bool => (bool) ($item['enabled'] ?? true))
            ->map(fn (array $item): array => [
                'title' => trim((string) ($item['title'] ?? '')),
                'url' => trim((string) ($item['url'] ?? '')),
                'cover_image_url' => $this->mediaUrl($item['cover_image_path'] ?? null),
            ])
            ->filter(fn (array $item): bool => filled($item['title']) && $this->isTopicUrl($item['url']))
            ->values()
            ->all();
    }

    private function isTopicUrl(string $url): bool
    {
        return Str::startsWith($url, '/topics/') && ! Str::contains($url, ['//', '..']);
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
