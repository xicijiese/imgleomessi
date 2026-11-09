<?php

namespace App\Services;

use Closure;

class CommentStickers
{
    public static function preview(string $value, int $limit = 160): string
    {
        return preg_replace('/\[dy:[^\]]*$/u', '…', str($value)->limit($limit)->toString());
    }

    public static function catalog(): array
    {
        static $items;

        return $items ??= array_column(json_decode(file_get_contents(resource_path('data/douyin-stickers.json')), true, 512, JSON_THROW_ON_ERROR), null, 'id');
    }

    public static function validate(string $attribute, mixed $value, Closure $fail): void
    {
        preg_match_all('/\\[dy:([^\\]\\r\\n]+)\\]/u', $value, $matches);
        if (count($matches[0]) > 20) {
            $fail('每条评论最多使用20个图片表情。');

            return;
        }
        foreach ($matches[1] as $id) {
            if (! isset(self::catalog()[$id])) {
                $fail('包含无效的表情，请从表情面板重新选择。');

                return;
            }
        }
        if (mb_strlen(trim($value)) < 2 && ! preg_match('/[\\x{1F000}-\\x{1FAFF}\\x{2600}-\\x{27BF}]/u', $value)) {
            $fail('评论至少填写2个字，或选择一个表情。');
        }
    }

    public static function scanText(string $value): string
    {
        return preg_replace_callback('/\\[dy:([a-f0-9]{16})\\]/', fn ($m) => self::catalog()[$m[1]]['name'] ?? $m[0], $value);
    }
}
