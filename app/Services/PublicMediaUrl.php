<?php

namespace App\Services;

use Illuminate\Support\Str;

class PublicMediaUrl
{
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
