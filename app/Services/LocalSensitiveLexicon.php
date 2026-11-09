<?php

namespace App\Services;

use App\Models\SensitiveWord;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class LocalSensitiveLexicon
{
    public function words(): array
    {
        $words = json_decode(file_get_contents(resource_path('data/sensitive-lexicon/words.json')), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($words) || $words === []) {
            throw new \RuntimeException('评论词库为空或格式错误');
        }

        return $words;
    }

    public function imported(): bool
    {
        return (bool) (Setting::value('moderation', 'lexicon_import', [])['completed'] ?? false);
    }

    public function import(bool $apply = false): array
    {
        if ($this->imported()) {
            return ['imported' => 0, 'skipped' => 0, 'completed' => true];
        }
        $existing = SensitiveWord::query()->pluck('word')->map(fn ($word) => CommentModeration::normalize($word))->flip()->all();
        $rows = [];
        $skipped = 0;
        $now = now();
        foreach ($this->words() as $word) {
            $key = CommentModeration::normalize($word);
            if ($key === '' || isset($existing[$key])) {
                $skipped++;

                continue;
            }
            $existing[$key] = true;
            $rows[] = ['word' => $word, 'severity' => 'medium', 'is_enabled' => true, 'internal_note' => '来源：konsheng/Sensitive-lexicon；本地筛选词库；版本68a595fb', 'created_at' => $now, 'updated_at' => $now];
        }
        if ($apply) {
            DB::transaction(function () use ($rows) {
                foreach (array_chunk($rows, 100) as $batch) {
                    SensitiveWord::query()->insert($batch);
                }
                Setting::setValue('moderation', 'lexicon_import', ['completed' => true, 'source_commit' => '68a595fb24d31bd9f882c081b4252443a69ac57b', 'imported_at' => now()->toISOString()], description: '本地词库已交由后台敏感词规则统一管理');
            });
        }

        return ['imported' => count($rows), 'skipped' => $skipped, 'completed' => false];
    }
}
