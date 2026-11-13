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

        return self::avifVariant($key, $variant);
    }

    public static function settingImage(mixed $path, string $variant = 'thumbnail_key'): ?string
    {
        $path = SettingImageVariants::sourceKey($path);
        if ($path === null) {
            return null;
        }
        $keys = SettingImageVariants::keys($path);

        return self::avifVariant($keys[$variant] ?? null, $variant);
    }

    private static function avifVariant(?string $key, string $variant): ?string
    {
        $url = self::fromPublicDisk($key);
        // 仅处理当前存储服务返回的云端 WebP 派生图，原图、外部地址、本地文件和签名地址不改。
        if (! config('media.cos_avif_enabled') || ! $url || ! $key
            || Str::startsWith($key, ['http://', 'https://'])
            || ! Str::startsWith($url, ['http://', 'https://'])
            || ! preg_match('/\.webp$/i', $url)) {
            return $url;
        }

        return $url.'/'.($variant === 'thumbnail_key' ? 'gallery_thumb_avif' : 'gallery_display_avif');
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
