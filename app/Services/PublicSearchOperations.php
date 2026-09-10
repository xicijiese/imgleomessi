<?php

namespace App\Services;

use App\Models\SearchQuery;
use App\Models\SearchRecommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicSearchOperations
{
    private const LIMIT = 8;

    private const HOT_MIN_COUNT = 2;

    /**
     * @param  array<string, mixed>  $searchPayload
     */
    public function record(Request $request, array $searchPayload, string $source = 'search_page'): void
    {
        $keyword = trim((string) $request->query('q', ''));
        $normalizedKeyword = $this->normalizeKeyword($keyword);

        if (! $this->isValidKeyword($normalizedKeyword)) {
            return;
        }

        $filters = Arr::get($searchPayload, 'filters', []);
        $resultCount = (int) Arr::get($searchPayload, 'photos.meta.total', 0);

        SearchQuery::query()->create([
            'user_id' => $request->user()?->id,
            'keyword' => Str::limit($keyword, 120, ''),
            'normalized_keyword' => $normalizedKeyword,
            'source' => Str::limit($source, 50, ''),
            'result_count' => max(0, $resultCount),
            'filters_json' => $this->filterSummary(is_array($filters) ? $filters : []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function pagePayload(): array
    {
        return [
            'recommendations' => $this->recommendations()->all(),
            'hot_terms' => $this->hotTerms()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function suggestions(Request $request): array
    {
        $keyword = $this->normalizeKeyword((string) $request->query('q', ''));
        $recommendations = $this->recommendations($keyword);
        $hotTerms = $this->isValidKeyword($keyword) ? $this->hotTerms($keyword) : collect();

        return [
            'items' => collect($recommendations->all())
                ->merge($hotTerms->all())
                ->unique(fn (array $item): string => $this->normalizeKeyword($item['term']))
                ->take(self::LIMIT)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recommendations(string $keyword = ''): Collection
    {
        $query = SearchRecommendation::query()
            ->active()
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->orderBy('id');

        if ($this->isValidKeyword($keyword)) {
            $like = $this->likeKeyword($keyword);
            $query->where(function ($query) use ($like): void {
                $query->where('keyword', 'like', "%{$like}%")
                    ->orWhere('title', 'like', "%{$like}%");
            });
        }

        return $query
            ->limit(self::LIMIT)
            ->get(['keyword', 'title', 'description', 'url'])
            ->map(fn (SearchRecommendation $recommendation): array => [
                'term' => $recommendation->keyword,
                'label' => $recommendation->title,
                'description' => $recommendation->description,
                'url' => $this->safeUrl($recommendation->url, $recommendation->keyword),
                'source' => 'recommendation',
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function hotTerms(string $keyword = ''): Collection
    {
        $query = SearchQuery::query()
            ->selectRaw('normalized_keyword, max(keyword) as keyword, count(*) as search_count')
            ->where('result_count', '>', 0)
            ->groupBy('normalized_keyword')
            ->havingRaw('count(*) >= ?', [self::HOT_MIN_COUNT])
            ->orderByDesc('search_count')
            ->orderBy('normalized_keyword');

        if ($this->isValidKeyword($keyword)) {
            $query->where('normalized_keyword', 'like', '%'.$this->likeKeyword($keyword).'%');
        }

        return $query
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (SearchQuery $query): array => [
                'term' => (string) $query->keyword,
                'label' => (string) $query->keyword,
                'description' => '热门搜索',
                'url' => $this->searchUrl((string) $query->keyword),
                'source' => 'hot',
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function filterSummary(array $filters): array
    {
        return collect(Arr::only($filters, [
            'categories',
            'tags',
            'people_tags',
            'album_id',
            'source_mode',
            'copyright_status',
            'orientation',
            'resolution',
            'watermark_status',
            'date_from',
            'date_to',
            'sort',
        ]))
            ->reject(fn (mixed $value): bool => $value === null || $value === '' || $value === [] || $value === 'all')
            ->all();
    }

    private function normalizeKeyword(string $keyword): string
    {
        $keyword = (string) preg_replace('/\s+/u', ' ', trim($keyword));

        return Str::lower(Str::limit($keyword, 120, ''));
    }

    private function isValidKeyword(string $keyword): bool
    {
        return mb_strlen($keyword) >= 2
            && mb_strlen($keyword) <= 120
            && preg_match('/[\pL\pN]/u', $keyword) === 1;
    }

    private function safeUrl(?string $url, string $keyword): string
    {
        if (is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '..')) {
            return $url;
        }

        return $this->searchUrl($keyword);
    }

    private function searchUrl(string $keyword): string
    {
        return '/search?q='.rawurlencode($keyword);
    }

    private function likeKeyword(string $keyword): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
    }
}
