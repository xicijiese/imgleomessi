<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Setting;
use App\Services\HomepageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_public_pages_and_excludes_private_or_non_public_content(): void
    {
        $publicPhoto = $this->publicPhoto('公开 SEO 图片', [
            'display_key' => 'photos/display/public-seo.webp',
        ]);
        $draftPhoto = $this->publicPhoto('草稿 SEO 图片', ['status' => 'draft']);
        $restrictedPhoto = $this->publicPhoto('受限 SEO 图片', ['copyright_status' => 'restricted']);
        $removeRequestedPhoto = $this->publicPhoto('下架 SEO 图片', ['copyright_status' => 'remove_requested']);

        $album = $this->publishedAlbum('公开 SEO 相册', ['slug' => 'public-seo-album']);
        $album->photos()->sync([$publicPhoto->id]);

        $emptyAlbum = $this->publishedAlbum('空 SEO 相册', ['slug' => 'empty-seo-album']);
        $draftAlbum = $this->publishedAlbum('草稿 SEO 相册', ['slug' => 'draft-seo-album', 'status' => 'draft']);
        $draftAlbum->photos()->sync([$publicPhoto->id]);

        $this->saveTopicModule([
            'items' => [
                ['enabled' => true, 'title' => '公开 SEO 专题', 'url' => '/topics/public-seo-topic'],
                ['enabled' => false, 'title' => '关闭 SEO 专题', 'url' => '/topics/disabled-seo-topic'],
                ['enabled' => true, 'title' => '', 'url' => '/topics/blank-seo-topic'],
                ['enabled' => true, 'title' => '异常 SEO 专题', 'url' => '/topics/../bad'],
            ],
        ]);

        $content = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);
        $this->assertStringContainsString('http://localhost/', $content);
        $this->assertStringContainsString('http://localhost/photos', $content);
        $this->assertStringContainsString('http://localhost/albums', $content);
        $this->assertStringContainsString('http://localhost/topics', $content);
        $this->assertStringContainsString('http://localhost/rankings', $content);
        $this->assertStringContainsString('http://localhost/support', $content);
        $this->assertStringContainsString('http://localhost/supporters', $content);
        $this->assertStringContainsString('http://localhost/about', $content);
        $this->assertStringContainsString('http://localhost/photos/'.$publicPhoto->uuid, $content);
        $this->assertStringContainsString('http://localhost/albums/public-seo-album', $content);
        $this->assertStringContainsString('http://localhost/topics/public-seo-topic', $content);

        $this->assertStringNotContainsString('http://localhost/photos/'.$draftPhoto->uuid, $content);
        $this->assertStringNotContainsString('http://localhost/photos/'.$restrictedPhoto->uuid, $content);
        $this->assertStringNotContainsString('http://localhost/photos/'.$removeRequestedPhoto->uuid, $content);
        $this->assertStringNotContainsString('http://localhost/albums/empty-seo-album', $content);
        $this->assertStringNotContainsString('http://localhost/albums/draft-seo-album', $content);
        $this->assertStringNotContainsString('http://localhost/topics/disabled-seo-topic', $content);
        $this->assertStringNotContainsString('http://localhost/topics/blank-seo-topic', $content);
        $this->assertStringNotContainsString('http://localhost/admin', $content);
        $this->assertStringNotContainsString('http://localhost/me', $content);
        $this->assertStringNotContainsString('http://localhost/settings', $content);
        $this->assertStringNotContainsString('http://localhost/search', $content);
    }

    public function test_robots_allows_public_pages_and_blocks_private_or_low_value_paths(): void
    {
        $content = $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Allow: /', $content);
        $this->assertStringContainsString('Disallow: /admin/', $content);
        $this->assertStringContainsString('Disallow: /me/', $content);
        $this->assertStringContainsString('Disallow: /settings/', $content);
        $this->assertStringContainsString('Disallow: /login', $content);
        $this->assertStringContainsString('Disallow: /register', $content);
        $this->assertStringContainsString('Disallow: /search', $content);
        $this->assertStringContainsString('Disallow: /support/result', $content);
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $content);
    }

    public function test_public_pages_receive_seo_payloads_for_head_meta(): void
    {
        $photo = $this->publicPhoto('世界杯 SEO 图片', [
            'description' => '梅西世界杯决赛相关图片。',
            'display_key' => 'photos/display/world-cup-seo.webp',
            'event_date' => '2022-12-18',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('home.seo.title', '首页')
                ->where('home.seo.canonical_url', 'http://localhost/')
                ->where('home.seo.robots', 'index, follow')
                ->where('home.seo.og.type', 'website')
            );

        $this->get('/photos/'.$photo->uuid.'?album=ignored-in-canonical')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Show')
                ->where('photoDetail.seo.title', '世界杯 SEO 图片')
                ->where('photoDetail.seo.description', '梅西世界杯决赛相关图片。事件日期：2022-12-18')
                ->where('photoDetail.seo.canonical_url', 'http://localhost/photos/'.$photo->uuid)
                ->where('photoDetail.seo.og.type', 'article')
                ->where('photoDetail.seo.og.image', 'http://localhost/storage/photos/display/world-cup-seo.webp')
            );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publicPhoto(string $title, array $attributes = []): Photo
    {
        return Photo::query()->create(array_merge([
            'title' => $title,
            'status' => 'published',
            'published_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedAlbum(string $title, array $attributes = []): Album
    {
        return Album::query()->create(array_merge([
            'title' => $title,
            'slug' => 'album-'.Str::uuid(),
            'status' => 'published',
            'published_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function saveTopicModule(array $overrides): void
    {
        $defaults = HomepageSettings::defaults()['topic_module'];

        Setting::setValue('home', 'topic_module', array_replace_recursive($defaults, $overrides));
    }
}
