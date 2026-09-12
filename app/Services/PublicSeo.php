<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PublicSeo
{
    private const PUBLIC_PHOTO_EXCLUDED_COPYRIGHT = ['restricted', 'remove_requested'];

    public function __construct(private readonly HomepageSettings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function home(): array
    {
        return $this->page(
            '首页',
            '按时间、赛事、球队、相册、标签和来源整理梅西影像资料，方便长期查阅和考古。',
            '/',
            $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function photosIndex(): array
    {
        return $this->page(
            '图库',
            '浏览已发布的梅西图片档案，通过分类、标签、相册和事件日期快速查找影像资料。',
            '/photos',
            $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function albumsIndex(): array
    {
        return $this->page(
            '相册',
            '按赛事、年份、球队和主题浏览已发布的梅西影像相册。',
            '/albums',
            $this->siteLogo(),
        );
    }

    /**
     * @param  array<string, mixed>  $album
     * @return array<string, mixed>
     */
    public function album(array $album): array
    {
        $title = trim((string) ($album['title'] ?? '相册'));
        $description = $this->description($album['description'] ?? null)
            ?? $title.'收录 '.(int) ($album['public_photos_count'] ?? 0).' 张公开梅西影像资料。';

        return $this->page(
            $title,
            $description,
            (string) ($album['url'] ?? '/albums'),
            $album['cover_image_url'] ?? $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function topicsIndex(): array
    {
        return $this->page(
            '专题',
            '按重要赛事、时间线和主题聚合浏览梅西影像专题。',
            '/topics',
            $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function dimensionIndex(string $label, string $path): array
    {
        return $this->page(
            $label,
            '按'.$label.'维度浏览已发布且可公开展示的梅西图片和相册资料。',
            $path,
            $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function dimensionDetail(string $label, string $categoryName, string $path): array
    {
        return $this->page(
            $categoryName,
            '浏览'.$categoryName.'相关的梅西公开影像资料，包含图片、相册和时间线入口。',
            $path,
            $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function userProfile(string $name, string $path): array
    {
        return $this->page(
            $name.'的公开主页 - 梅西影像档案库',
            '查看'.$name.'在梅西影像档案库中的公开运营守护者身份、勋章和公开评论摘要。',
            $path,
            $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function timelineIndex(): array
    {
        return $this->page(
            '时间线',
            '按事件日期浏览已公开的梅西影像资料，快速进入年份和月份档案。',
            '/timeline',
            $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function timelineYear(int $year): array
    {
        return $this->page(
            $year.' 年时间线',
            '浏览 '.$year.' 年已发布且可公开展示的梅西图片、相册和相关专题线索。',
            '/timeline/'.$year,
            $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function timelineMonth(int $year, int $month): array
    {
        $monthLabel = str_pad((string) $month, 2, '0', STR_PAD_LEFT).' 月';

        return $this->page(
            $year.' 年 '.$monthLabel.' 时间线',
            '浏览 '.$year.' 年 '.$monthLabel.' 已发布且可公开展示的梅西图片和相册。',
            '/timeline/'.$year.'/'.str_pad((string) $month, 2, '0', STR_PAD_LEFT),
            $this->siteLogo(),
        );
    }

    /**
     * @param  array<string, mixed>  $topic
     * @return array<string, mixed>
     */
    public function topic(array $topic): array
    {
        $title = trim((string) ($topic['title'] ?? '专题'));

        return $this->page(
            $title,
            $this->description($topic['description'] ?? null) ?? $title.'相关梅西影像专题资料。',
            (string) ($topic['url'] ?? '/topics'),
            $topic['cover_image_url'] ?? $this->siteLogo(),
        );
    }

    /**
     * @param  array<string, mixed>  $photo
     * @return array<string, mixed>
     */
    public function photo(array $photo): array
    {
        $title = trim((string) ($photo['title'] ?? '梅西图片'));
        $parts = collect([
            $this->sentencePart($this->description($photo['description'] ?? null)),
            filled($photo['event_date'] ?? null) ? '事件日期：'.$photo['event_date'] : null,
            collect($photo['categories'] ?? [])->pluck('name')->filter()->take(4)->implode(' / '),
            collect($photo['tags'] ?? [])->pluck('name')->filter()->take(4)->implode(' / '),
        ])->filter()->implode('。');

        return $this->page(
            $title,
            $parts !== '' ? $parts : $title.'相关梅西公开影像资料。',
            (string) ($photo['url'] ?? '/photos'),
            $photo['image_url'] ?? $this->siteLogo(),
            'article',
        );
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function rankings(array $summary = []): array
    {
        $scoreLabel = (string) ($summary['score_label'] ?? '综合热度');

        return $this->page(
            '互动排行榜',
            '查看梅西图片的'.$scoreLabel.'排行，统计点赞、收藏、评论和分享等公开互动数据。',
            '/rankings',
            $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function support(): array
    {
        return $this->page(
            '支持本站',
            '通过赞助支持梅西影像档案库的长期整理和维护；赞助不是购买图片版权或原图下载资格。',
            '/support',
            $this->siteLogo(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function supporters(): array
    {
        return $this->page(
            '致谢墙',
            '展示愿意公开的运营守护者和档案共建者信息，不包含支付流水、邮箱或后台备注等敏感数据。',
            '/supporters',
            $this->siteLogo(),
        );
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public function staticPage(array $page): array
    {
        return $this->page(
            (string) ($page['title'] ?? '站点说明'),
            $this->description($page['description'] ?? null) ?? '梅西影像档案库站点说明。',
            '/'.(string) ($page['key'] ?? 'about'),
            $this->siteLogo(),
        );
    }

    public function robots(Request $request): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /me/',
            'Disallow: /settings/',
            'Disallow: /dashboard',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /confirm-password',
            'Disallow: /two-factor-challenge',
            'Disallow: /email/verification-notification',
            'Disallow: /search',
            'Disallow: /support/result',
            'Sitemap: '.$this->absoluteUrl('/sitemap.xml', $request),
            '',
        ]);
    }

    public function sitemap(Request $request): string
    {
        $urls = collect($this->sitemapEntries($request))
            ->map(function (array $entry): string {
                $xml = '    <loc>'.e($entry['loc']).'</loc>';

                if (filled($entry['lastmod'] ?? null)) {
                    $xml .= "\n".'    <lastmod>'.e((string) $entry['lastmod']).'</lastmod>';
                }

                return "  <url>\n{$xml}\n  </url>";
            })
            ->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$urls}
</urlset>
XML;
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $title, string $description, string $path, ?string $image = null, string $type = 'website'): array
    {
        $canonicalUrl = $this->absoluteUrl($this->pathOnly($path));
        $description = $this->description($description) ?? '梅西影像档案库';
        $imageUrl = $image !== null ? $this->absoluteUrl($image) : null;
        $fullTitle = $title.' - '.$this->siteName();

        return [
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonicalUrl,
            'robots' => 'index, follow',
            'og' => [
                'title' => $fullTitle,
                'description' => $description,
                'type' => $type,
                'url' => $canonicalUrl,
                'image' => $imageUrl,
            ],
            'twitter' => [
                'card' => $imageUrl !== null ? 'summary_large_image' : 'summary',
                'title' => $fullTitle,
                'description' => $description,
                'image' => $imageUrl,
            ],
        ];
    }

    /**
     * @return array<int, array{loc: string, lastmod?: string|null}>
     */
    private function sitemapEntries(Request $request): array
    {
        $entries = collect([
            ['loc' => $this->absoluteUrl('/', $request)],
            ['loc' => $this->absoluteUrl('/photos', $request)],
            ['loc' => $this->absoluteUrl('/albums', $request)],
            ['loc' => $this->absoluteUrl('/topics', $request)],
            ['loc' => $this->absoluteUrl('/teams', $request)],
            ['loc' => $this->absoluteUrl('/seasons', $request)],
            ['loc' => $this->absoluteUrl('/rankings', $request)],
            ['loc' => $this->absoluteUrl('/support', $request)],
            ['loc' => $this->absoluteUrl('/supporters', $request)],
            ['loc' => $this->absoluteUrl('/about', $request)],
            ['loc' => $this->absoluteUrl('/copyright', $request)],
            ['loc' => $this->absoluteUrl('/takedown', $request)],
            ['loc' => $this->absoluteUrl('/privacy', $request)],
            ['loc' => $this->absoluteUrl('/terms', $request)],
        ]);

        $photoEntries = Photo::query()
            ->published()
            ->whereNotIn('copyright_status', self::PUBLIC_PHOTO_EXCLUDED_COPYRIGHT)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['uuid', 'updated_at', 'published_at'])
            ->map(fn (Photo $photo): array => [
                'loc' => $this->absoluteUrl('/photos/'.$photo->uuid, $request),
                'lastmod' => $this->date($photo->updated_at ?? $photo->published_at),
            ]);

        $albumEntries = Album::query()
            ->published()
            ->whereHas('photos', fn (Builder $query): Builder => $this->publicPhotoConstraint($query))
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->get(['slug', 'updated_at', 'published_at'])
            ->map(fn (Album $album): array => [
                'loc' => $this->absoluteUrl('/albums/'.$album->slug, $request),
                'lastmod' => $this->date($album->updated_at ?? $album->published_at),
            ]);

        $dimensionEntries = Category::query()
            ->children()
            ->where('visibility', 'public')
            ->whereHas('parent', fn (Builder $query): Builder => $query
                ->whereIn('slug', ['career-stage', 'season'])
                ->where('visibility', 'public'))
            ->whereHas('photos', fn (Builder $query): Builder => $this->publicPhotoConstraint($query))
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'slug', 'updated_at'])
            ->map(fn (Category $category): array => [
                'loc' => $this->absoluteUrl(($category->parent?->slug === 'season' ? '/seasons/' : '/teams/').$category->slug, $request),
                'lastmod' => $this->date($category->updated_at),
            ]);

        $topicEntries = collect(Arr::get($this->settings->formState(), 'topic_module.items', []))
            ->filter(fn (array $topic): bool => $this->isPublicTopic($topic))
            ->map(fn (array $topic): array => [
                'loc' => $this->absoluteUrl((string) $topic['url'], $request),
            ]);

        return $entries
            ->concat($photoEntries)
            ->concat($albumEntries)
            ->concat($dimensionEntries)
            ->concat($topicEntries)
            ->unique('loc')
            ->values()
            ->all();
    }

    private function publicPhotoConstraint($query)
    {
        return $query
            ->where('photos.status', 'published')
            ->whereNotIn('photos.copyright_status', self::PUBLIC_PHOTO_EXCLUDED_COPYRIGHT);
    }

    /**
     * @param  array<string, mixed>  $topic
     */
    private function isPublicTopic(array $topic): bool
    {
        $url = trim((string) ($topic['url'] ?? ''));

        return (bool) ($topic['enabled'] ?? true)
            && filled($topic['title'] ?? null)
            && Str::startsWith($url, '/topics/')
            && ! Str::contains($url, ['//', '..']);
    }

    private function date(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toDateString() : null;
    }

    private function sentencePart(?string $value): ?string
    {
        return is_string($value) ? preg_replace('/[。.!！?？]+$/u', '', $value) : null;
    }

    private function description(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $text = preg_replace('/\s+/u', ' ', strip_tags($value));
        $text = trim((string) $text);

        return $text !== '' ? Str::limit($text, 150, '') : null;
    }

    private function siteName(): string
    {
        return (string) Arr::get($this->settings->formState(), 'site.name', '梅西影像档案库');
    }

    private function siteLogo(): ?string
    {
        return PublicMediaUrl::fromPublicDisk(Arr::get($this->settings->formState(), 'site.logo_path'));
    }

    private function pathOnly(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    private function absoluteUrl(string $url, ?Request $request = null): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $path = '/'.ltrim($this->pathOnly($url), '/');
        $base = $request instanceof Request
            ? rtrim($request->getSchemeAndHttpHost(), '/')
            : rtrim(config('app.url'), '/');

        return $base.$path;
    }
}
