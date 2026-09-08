<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SensitiveWord extends Model
{
    public const SEVERITIES = [
        'low' => '低风险',
        'medium' => '中风险',
        'high' => '高风险',
    ];

    protected $fillable = [
        'word',
        'severity',
        'is_enabled',
        'internal_note',
    ];

    protected $attributes = [
        'severity' => 'medium',
        'is_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * @return array{risk_level: string, hits: array<int, array{word: string, severity: string}>}
     */
    public static function scan(?string ...$texts): array
    {
        $content = mb_strtolower(implode("\n", array_filter($texts, fn (?string $text): bool => filled($text))));

        if ($content === '') {
            return ['risk_level' => 'clean', 'hits' => []];
        }

        $severityRank = ['clean' => 0, 'low' => 1, 'medium' => 2, 'high' => 3];
        $riskLevel = 'clean';
        $hits = [];

        static::query()
            ->enabled()
            ->get(['word', 'severity'])
            ->each(function (SensitiveWord $rule) use ($content, $severityRank, &$riskLevel, &$hits): void {
                $word = trim($rule->word);

                if ($word === '' || ! str_contains($content, mb_strtolower($word))) {
                    return;
                }

                $severity = array_key_exists($rule->severity, self::SEVERITIES) ? $rule->severity : 'medium';
                $hits[$word] = ['word' => $word, 'severity' => $severity];

                if ($severityRank[$severity] > $severityRank[$riskLevel]) {
                    $riskLevel = $severity;
                }
            });

        return [
            'risk_level' => $riskLevel,
            'hits' => array_values($hits),
        ];
    }
}
