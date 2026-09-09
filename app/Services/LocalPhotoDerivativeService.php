<?php

namespace App\Services;

use App\Models\Photo;
use RuntimeException;

class LocalPhotoDerivativeService
{
    public function __construct(private readonly PhotoStorage $storage) {}

    /**
     * @return array{display_key: string, thumbnail_key: string}
     */
    public function generate(Photo $photo): array
    {
        if (blank($photo->original_key)) {
            throw new RuntimeException('图片缺少原图存储 Key。');
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('本地图片处理需要 PHP GD 扩展及 WebP 支持。');
        }

        $disk = $this->storage->diskForKey($photo->original_key);
        $sourceContents = $disk->get($photo->original_key);
        $source = @imagecreatefromstring($sourceContents);

        if ($source === false) {
            throw new RuntimeException('本地无法读取原图内容。');
        }

        $displayKey = 'photos/derived/'.$photo->uuid.'/display.webp';
        $thumbnailKey = 'photos/derived/'.$photo->uuid.'/thumbnail.webp';

        try {
            $displayContents = $this->renderWebp($source, 2048, 2048, 86);
            $thumbnailContents = $this->renderWebp($source, 600, 600, 82);

            if (! $disk->put($displayKey, $displayContents)
                || ! $disk->put($thumbnailKey, $thumbnailContents)) {
                throw new RuntimeException('本地展示图或缩略图写入失败。');
            }
        } finally {
            imagedestroy($source);
        }

        return [
            'display_key' => $displayKey,
            'thumbnail_key' => $thumbnailKey,
        ];
    }

    private function renderWebp(\GdImage $source, int $maxWidth, int $maxHeight, int $quality): string
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth < 1 || $sourceHeight < 1) {
            throw new RuntimeException('原图尺寸无效。');
        }

        $scale = min(1, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            throw new RuntimeException('本地无法创建图片处理画布。');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        ob_start();
        $encoded = imagewebp($canvas, null, $quality);
        $contents = ob_get_clean();
        imagedestroy($canvas);

        if (! $encoded || ! is_string($contents) || $contents === '') {
            throw new RuntimeException('本地无法生成 WebP 图片。');
        }

        return $contents;
    }
}