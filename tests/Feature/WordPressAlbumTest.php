<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Photo;
use App\Services\SharePromotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordPressAlbumTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'wordpress-test-token-with-at-least-32-characters';

    protected function setUp(): void
    {
        parent::setUp();
        config(['wordpress.token_hash' => hash('sha256', $this->token)]);
    }

    private function album(): Album
    {
        $album = Album::create(['title' => '比赛组图', 'slug' => 'match-album', 'status' => 'published']);
        $photo = Photo::create(['title' => '公开图', 'status' => 'published', 'original_key' => 'private/original.jpg',
            'display_key' => 'derived/display.webp', 'thumbnail_key' => 'derived/thumb.webp']);
        $album->photos()->attach($photo);

        return $album;
    }

    public function test_access_is_denied_by_default_and_revocation_takes_effect(): void
    {
        $this->getJson('/api/wordpress/v1/health')->assertUnauthorized();
        $this->withToken('incorrect')->getJson('/api/wordpress/v1/health')->assertUnauthorized();
        $this->withToken($this->token)->getJson('/api/wordpress/v1/health')->assertOk()->assertJsonPath('version', 1);
        config(['wordpress.token_hash' => '']);
        $this->getJson('/api/wordpress/v1/health')->assertUnauthorized();
    }

    public function test_diagnostics_distinguish_missing_header_configuration_and_mismatch_without_exposing_secrets(): void
    {
        $this->getJson('/api/wordpress/v1/health')->assertUnauthorized()->assertJsonPath('code', 'wordpress_token_missing');
        $response = $this->withToken(str_repeat('x', 64))->getJson('/api/wordpress/v1/health')
            ->assertUnauthorized()->assertJsonPath('code', 'wordpress_token_mismatch');
        $this->assertStringNotContainsString($this->token, $response->getContent());
        $this->assertStringNotContainsString(config('wordpress.token_hash'), $response->getContent());
        config(['wordpress.token_hash' => '']);
        $this->getJson('/api/wordpress/v1/health')->assertUnauthorized()->assertJsonPath('code', 'wordpress_not_configured');
    }

    public function test_configuration_check_reports_stale_config_without_printing_hashes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wp-album-');
        $hash = hash('sha256', 'different-test-token');
        file_put_contents($path, 'WORDPRESS_ALBUM_TOKEN_HASH='.$hash.PHP_EOL);
        $this->app->useEnvironmentPath(dirname($path));
        $this->app->loadEnvironmentFrom(basename($path));
        try {
            $this->artisan('wordpress:album-check')
                ->expectsOutput('当前接口配置：已加载有效格式')
                ->expectsOutput('环境文件配置：有效格式')
                ->expectsOutput('当前配置与环境文件：不一致，请核对缓存及服务器环境变量')
                ->doesntExpectOutput($hash)
                ->assertSuccessful();
        } finally {
            unlink($path);
        }
    }

    public function test_short_link_resolves_without_recording_a_visit_or_operation(): void
    {
        $album = $this->album();
        $link = app(SharePromotion::class)->link(null, 'album', $album->slug, 'copy_link');
        $this->withToken($this->token)->getJson('/api/wordpress/v1/resolve?'.http_build_query(['url' => 'http://localhost/s/'.$link->code]))
            ->assertOk()->assertJsonPath('album.id', $album->id)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(0, (int) $link->fresh()->visits);
        $this->assertSame(0, (int) $link->fresh()->operations);
        $this->assertDatabaseCount('share_visits', 0);
    }

    public function test_only_own_album_links_are_accepted_and_disabled_links_are_rejected(): void
    {
        $album = $this->album();
        $this->withToken($this->token);
        foreach (['https://evil.test/albums/match-album', 'http://localhost.evil.test/albums/match-album', 'http://x@localhost/albums/match-album', 'http://localhost/photos/abc'] as $url) {
            $this->getJson('/api/wordpress/v1/resolve?'.http_build_query(['url' => $url]))->assertUnprocessable();
        }
        $this->getJson('/api/wordpress/v1/resolve?'.http_build_query(['url' => 'http://localhost/albums/'.$album->slug.'?via=abc']))->assertOk();
        $link = app(SharePromotion::class)->link(null, 'album', $album->slug, 'copy_link');
        $link->update(['is_active' => false]);
        $this->getJson('/api/wordpress/v1/resolve?'.http_build_query(['url' => 'http://localhost/s/'.$link->code]))->assertGone();
        $photoLink = app(SharePromotion::class)->link(null, 'photo', $album->photos()->first()->uuid, 'copy_link');
        $this->getJson('/api/wordpress/v1/resolve?'.http_build_query(['url' => 'http://localhost/s/'.$photoLink->code]))->assertUnprocessable();
    }

    public function test_pagination_returns_only_public_derivatives_with_absolute_urls(): void
    {
        $album = $this->album();
        foreach ([['status' => 'draft'], ['status' => 'archived'], ['copyright_status' => 'restricted'], ['copyright_status' => 'remove_requested'], ['display_key' => 'private/original.jpg'], ['thumbnail_key' => null]] as $attributes) {
            $photo = Photo::create(array_merge(['title' => '不能泄露', 'status' => 'published', 'original_key' => 'private/original.jpg', 'display_key' => 'derived/d.webp', 'thumbnail_key' => 'derived/t.webp'], $attributes));
            $album->photos()->attach($photo);
        }
        $second = Photo::create(['title' => '第二张', 'status' => 'published', 'original_key' => 'private/second.jpg', 'display_key' => 'derived/second.webp', 'thumbnail_key' => 'derived/second-t.webp']);
        $album->photos()->attach($second);
        $first = $this->withToken($this->token)->getJson('/api/wordpress/v1/albums/'.$album->id.'?per_page=1')->assertOk()
            ->assertJsonCount(1, 'photos')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('photos.0.thumbnail_url', 'http://localhost/storage/derived/thumb.webp');
        $this->assertStringNotContainsString('private/', $first->getContent());
        $this->assertStringNotContainsString('original_key', $first->getContent());
        $secondPage = $this->getJson('/api/wordpress/v1/albums/'.$album->id.'?per_page=1&page=2')->assertOk()->assertJsonPath('photos.0.id', $second->uuid);
        $this->assertSame($first->json('revision'), $secondPage->json('revision'));
        $second->update(['copyright_status' => 'remove_requested']);
        $changed = $this->getJson('/api/wordpress/v1/albums/'.$album->id)->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertNotSame($first->json('revision'), $changed->json('revision'));
        $this->getJson('/api/wordpress/v1/albums/'.$album->id.'?per_page=101')->assertUnprocessable();
        $this->getJson('/api/wordpress/v1/albums/'.$album->id.'?page=0')->assertUnprocessable();
        $album->update(['status' => 'hidden']);
        $this->getJson('/api/wordpress/v1/albums/'.$album->id)->assertNotFound();
    }
}
