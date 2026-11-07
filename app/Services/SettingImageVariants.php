<?php

namespace App\Services;

use App\Jobs\GeneratePublicImageVariants;
use App\Models\Setting;
use Illuminate\Support\Str;

class SettingImageVariants
{
    public static function keys(string $path): array
    {
        $keys = Setting::value('image_variants', hash('sha256', $path), []);
        if (empty($keys['thumbnail_key']) || empty($keys['display_key'])
            || $keys['thumbnail_key'] === $keys['display_key']
            || in_array($path, [$keys['thumbnail_key'], $keys['display_key']], true)) {
            return [];
        }

        return $keys;
    }

    public static function sourceKey(mixed $path): ?string
    {
        if (! is_string($path) || str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }
        $path = ltrim($path, '/');
        $path = Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path;

        return Str::startsWith($path, ['settings/', 'photos/', 'demo-gallery/']) ? $path : null;
    }

    public function pendingSources(array $state): array
    {
        $sources = [];
        foreach ($state['hero_slides'] ?? [] as $slide) {
            $sources[] = $slide['desktop_image_path'] ?? null;
            $sources[] = $slide['mobile_image_path'] ?? null;
        }
        foreach ($state['topic_module']['items'] ?? [] as $item) {
            $sources[] = $item['cover_image_path'] ?? null;
        }

        return collect($sources)->map(fn ($path) => self::sourceKey($path))->filter()->unique()
            ->filter(fn ($path) => ! self::keys($path))
            ->values()->all();
    }

    public function queueForSettings(array $state): void
    {
        foreach ($this->pendingSources($state) as $source) {
            GeneratePublicImageVariants::dispatch(null, $source);
        }
    }
}
