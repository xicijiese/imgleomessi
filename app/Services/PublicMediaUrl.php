<?php

namespace App\Services;

use App\Models\Photo;
use Illuminate\Support\Str;

class PublicMediaUrl
{
    public static function thumbnail(?Photo $photo): ?string
    {
        return self::photoVariant($photo, 'thumbnail_key');
    }

    public static function display(?Photo $photo): ?string
    {
        return self::photoVariant($photo, 'display_key') ?? self::thumbnail($photo);
    }

    private static function photoVariant(?Photo $photo, string $variant): ?string
    {
        $key = $photo?->getAttribute($variant);
        if (blank($key) || $key === $photo?->getRawOriginal('original_key')
            || ($variant === 'thumbnail_key' && $key === $photo?->getRawOriginal('display_key'))) {
            return null;
        }

        return self::fromPublicDisk($key);
    }

    public static function settingImage(mixed $path, string $variant = 'thumbnail_key'): ?string
    {
        $path = SettingImageVariants::sourceKey($path);
        if ($path === null) {
            return null;
        }
        $keys = SettingImageVariants::keys($path);

        return self::fromPublicDisk($keys[$variant] ?? null);
    }

    public static function fromPublicDisk(mixed $path): ?string
    {
        if (! is_string($path) || blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return app(PhotoStorage::class)->url($path);
    }
}
