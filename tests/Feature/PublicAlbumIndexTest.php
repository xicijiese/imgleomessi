<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicAlbumIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_album_index_with_default_payload(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $photo = $this->publicPhoto('相册封面', [
            'display_key' => 'photos/display/album-cover.webp',
        ]);
        $album = $this->publishedAlbum('2022 世界杯决赛', [
            'slug' => '2022-world-cup-final',
            'cover_photo_id' => $photo->id,
        ]);
        $album->photos()->sync([$photo->id]);
        $album->categories()->sync($this->pendingCategoryIds());

        $this->get('/albums')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Index')
                ->where('albumIndex.site.name', '梅西影像档案库')
                ->where('albumIndex.filters.sort', 'default')
                ->has('albumIndex.filter_options.category_groups', 8)
                ->has('albumIndex.albums.data', 1)
                ->where('albumIndex.albums.data.0.title', '2022 世界杯决赛')
                ->where('albumIndex.albums.data.0.url', '/albums/2022-world-cup-final')
                ->where('albumIndex.albums.data.0.public_photos_count', 1)
                ->where('albumIndex.albums.data.0.cover_image_url', '/storage/photos/display/album-cover.webp')
            );
    }

    public function test_album_index_only_exposes_published_albums_with_public_photos(): void
    {
        $publicPhoto = $this->publicPhoto('公开图片');
        $draftPhoto = $this->publicPhoto('草稿相册图片');
        $hiddenPhoto = $this->publicPhoto('隐藏相册图片');
        $restrictedPhoto = $this->publicPhoto('受限图片', [
            'copyright_status' => 'restricted',
        ]);

        $publicAlbum = $this->publishedAlbum('公开相册');
        $publicAlbum->photos()->sync([$publicPhoto->id]);

        $draftAlbum = $this->publishedAlbum('草稿相册', ['status' => 'draft']);
        $draftAlbum->photos()->sync([$draftPhoto->id]);

        $hiddenAlbum = $this->publishedAlbum('隐藏相册', ['status' => 'hidden']);
        $hiddenAlbum->photos()->sync([$hiddenPhoto->id]);

        $emptyAlbum = $this->publishedAlbum('空相册');
        $restrictedAlbum = $this->publishedAlbum('只有受限图片的相册');
        $restrictedAlbum->photos()->sync([$restrictedPhoto->id]);

        $this->get('/albums')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Index')
                ->has('albumIndex.albums.data', 1)
                ->where('albumIndex.albums.data.0.title', '公开相册')
                ->where('albumIndex.albums.meta.total', 1)
            );
    }

    public function test_album_index_filters_by_keyword_and_category(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $competition = Category::query()->roots()->where('slug', 'competition')->firstOrFail();
        $worldCup = Category::query()->children()->where('parent_id', $competition->id)->firstOrFail();
        $season = Category::query()->roots()->where('slug', 'season')->firstOrFail();
        $seasonChild = Category::query()->children()->where('parent_id', $season->id)->firstOrFail();

        $matching = $this->publishedAlbum('World Cup final album', [
            'description' => '阿根廷夺冠夜',
            'slug' => 'world-cup-final-album',
        ]);
        $matching->photos()->sync([$this->publicPhoto('匹配相册图片')->id]);
        $matching->categories()->sync([$worldCup->id]);

        $sameKeywordWrongCategory = $this->publishedAlbum('World Cup training album', [
            'slug' => 'world-cup-training-album',
        ]);
        $sameKeywordWrongCategory->photos()->sync([$this->publicPhoto('其他相册图片')->id]);
        $sameKeywordWrongCategory->categories()->sync([$seasonChild->id]);

        $query = http_build_query([
            'q' => 'World Cup',
            'categories' => ['competition' => $worldCup->id],
        ]);

        $this->get('/albums?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Index')
                ->where('albumIndex.filters.q', 'World Cup')
                ->where('albumIndex.filters.categories.competition', $worldCup->id)
                ->has('albumIndex.albums.data', 1)
                ->where('albumIndex.albums.data.0.title', 'World Cup final album')
            );
    }

    public function test_album_index_uses_manual_public_cover_and_falls_back_when_cover_is_not_public(): void
    {
        $manualCover = $this->publicPhoto('手动封面', [
            'display_key' => 'photos/display/manual-cover.webp',
            'published_at' => now()->subDay(),
        ]);
        $otherPhoto = $this->publicPhoto('普通公开图', [
            'display_key' => 'photos/display/other-cover.webp',
            'published_at' => now(),
        ]);
        $manualAlbum = $this->publishedAlbum('manual cover album', [
            'cover_photo_id' => $manualCover->id,
        ]);
        $manualAlbum->photos()->sync([$manualCover->id, $otherPhoto->id]);

        $this->get('/albums?q=manual')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Index')
                ->has('albumIndex.albums.data', 1)
                ->where('albumIndex.albums.data.0.cover_image_url', '/storage/photos/display/manual-cover.webp')
            );

        $restrictedCover = $this->publicPhoto('不可公开封面', [
            'copyright_status' => 'restricted',
            'display_key' => 'photos/display/restricted-cover.webp',
        ]);
        $fallbackCover = $this->publicPhoto('兜底封面', [
            'display_key' => 'photos/display/fallback-cover.webp',
        ]);
        $fallbackAlbum = $this->publishedAlbum('fallback cover album', [
            'cover_photo_id' => $restrictedCover->id,
        ]);
        $fallbackAlbum->photos()->sync([$restrictedCover->id, $fallbackCover->id]);

        $this->get('/albums?q=fallback')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Index')
                ->has('albumIndex.albums.data', 1)
                ->where('albumIndex.albums.data.0.cover_image_url', '/storage/photos/display/fallback-cover.webp')
            );
    }

    public function test_album_index_sorts_and_ignores_invalid_filter_values_without_error(): void
    {
        $older = $this->publishedAlbum('较早相册', [
            'published_at' => now()->subDays(2),
        ]);
        $older->photos()->sync([$this->publicPhoto('较早相册图片')->id]);
        $newer = $this->publishedAlbum('较新相册', [
            'published_at' => now()->subDay(),
        ]);
        $newer->photos()->sync([$this->publicPhoto('较新相册图片')->id]);

        $this->get('/albums?sort=published_asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Index')
                ->where('albumIndex.filters.sort', 'published_asc')
                ->where('albumIndex.albums.data.0.title', '较早相册')
                ->where('albumIndex.albums.data.1.title', '较新相册')
            );

        $query = http_build_query([
            'categories' => ['competition' => 99999],
            'sort' => 'unknown-sort',
        ]);

        $this->get('/albums?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Index')
                ->where('albumIndex.filters.categories', [])
                ->where('albumIndex.filters.sort', 'default')
                ->has('albumIndex.albums.data', 2)
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
     * @return array<int, int>
     */
    private function pendingCategoryIds(): array
    {
        return Category::query()
            ->children()
            ->where('name', '待补充')
            ->pluck('id')
            ->all();
    }
}
