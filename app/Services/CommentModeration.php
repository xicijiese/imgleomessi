<?php

namespace App\Services;

use App\Models\SensitiveWord;
use Illuminate\Validation\ValidationException;

class CommentModeration
{
    public static function normalize(string $text): string
    {
        return mb_strtolower(preg_replace('/[\p{Z}\p{Cf}\s]+/u', '', mb_convert_kana($text, 'asKV', 'UTF-8')));
    }

    public function ensureAllowed(string $content): void
    {
        // 已收录表情不按名称检测；移除标记后继续检测文字，避免插表情拆词绕过。
        $text = preg_replace_callback('/\[dy:([a-f0-9]{16})\]/', fn ($match) => isset(CommentStickers::catalog()[$match[1]]) ? '' : $match[0], $content);
        $readable = mb_strtolower(preg_replace('/\p{Cf}/u', '', mb_convert_kana($text, 'asKV', 'UTF-8')));
        $content = self::normalize($readable);
        // 首次导入完成后，以后台规则为准，编辑或删除后不再从文件补回旧词。
        $overrides = SensitiveWord::query()->get(['word', 'is_enabled'])->mapWithKeys(fn ($rule) => [self::normalize($rule->word) => (bool) $rule->is_enabled])->all();
        $lexicon = app(LocalSensitiveLexicon::class);
        $words = $lexicon->imported() ? [] : $lexicon->words();
        $rules = array_fill_keys(array_filter(array_map(self::normalize(...), $words)), true);
        foreach ($overrides as $word => $enabled) {
            if ($word !== '') {
                $rules[$word] = $enabled;
            }
        }
        foreach (array_chunk(array_keys(array_filter($rules)), 400) as $batch) {
            $patterns = array_map(function ($word) {
                $escaped = preg_quote((string) $word, '~');

                return preg_match('/^[a-z0-9]+$/', (string) $word) ? '(?<![a-z0-9])'.$escaped.'(?![a-z0-9])' : $escaped;
            }, $batch);
            $matched = preg_match('~(?:'.implode('|', $patterns).')~u', $content);
            $spacedMatch = preg_match('~(?:'.implode('|', $patterns).')~u', $readable);
            if ($matched === false || $spacedMatch === false) {
                throw new \RuntimeException('评论词库匹配失败');
            }
            if ($matched === 1 || $spacedMatch === 1) {
                throw ValidationException::withMessages(['content' => '评论包含不适宜内容，请修改后重新发布。']);
            }
        }
    }
}
