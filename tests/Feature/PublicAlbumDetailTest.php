<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Tag;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicAlbumDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_published_album_detail_with_public_photos(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $tag = Tag::query()->create([
            'name' => '捧杯',
            'type' => '荣誉',
            'sort_order' => 10,
        ]);
        $photo = $this->publicPhoto('世界杯决赛捧杯', [
            'display_key' => 'photos/display/world-cup-final.webp',
            'event_date' => '2022-12-18',
        ]);
        $photo->categories()->sync($this->pendingCategoryIds());
        $photo->tags()->sync([$tag->id]);

        $album = $this->publishedAlbum('2022 世界杯决赛', [
            'slug' => '2022-world-cup-final',
            'description' => '阿根廷夺冠夜相册',
            'cover_photo_id' => $photo->id,
        ]);
        $album->categories()->sync($this->pendingCategoryIds());
        $album->photos()->sync([$photo->id]);

        $this->get('/albums/2022-world-cup-final')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Show')
                ->where('albumDetail.site.name', '梅西影像档案库')
                ->where('albumDetail.filters.sort', 'event_desc')
                ->where('albumDetail.album.title', '2022 世界杯决赛')
                ->where('albumDetail.album.slug', '2022-world-cup-final')
                ->where('albumDetail.album.description', '阿根廷夺冠夜相册')
                ->where('albumDetail.album.public_photos_count', 1)
                ->where('albumDetail.album.cover_image_url', '/storage/photos/display/world-cup-final.webp')
                ->has('albumDetail.album.categories', 8)
                ->has('albumDetail.photos.data', 1)
                ->where('albumDetail.photos.data.0.title', '世界杯决赛捧杯')
                ->where('albumDetail.photos.data.0.url', '/photos/'.$photo->uuid.'?album=2022-world-cup-final')
                ->where('albumDetail.photos.data.0.image_url', '/storage/photos/display/world-cup-final.webp')
                ->where('albumDetail.photos.data.0.event_date', '2022-12-18')
                ->where('albumDetail.photos.data.0.tags.0.name', '捧杯')
            );
    }

    public function test_album_detail_rejects_draft_hidden_missing_and_empty_public_album(): void
    {
        $publicPhoto = $this->publicPhoto('公开图片');

        $draftAlbum = $this->publishedAlbum('草稿相册', ['slug' => 'draft-album', 'status' => 'draft']);
        $draftAlbum->photos()->sync([$publicPhoto->id]);

        $hiddenAlbum = $this->publishedAlbum('隐藏相册', ['slug' => 'hidden-album', 'status' => 'hidden']);
        $hiddenAlbum->photos()->sync([$publicPhoto->id]);

        $emptyAlbum = $this->publishedAlbum('空相册', ['slug' => 'empty-album']);

        $restrictedPhoto = $this->publicPhoto('受限图片', ['copyright_status' => 'restricted']);
        $restrictedAlbum = $this->publishedAlbum('只有受限图片的相册', ['slug' => 'restricted-album']);
        $restrictedAlbum->photos()->sync([$restrictedPhoto->id]);

        $this->get('/albums/draft-album')->assertNotFound();
        $this->get('/albums/hidden-album')->assertNotFound();
        $this->get('/albums/empty-album')->assertNotFound();
        $this->get('/albums/restricted-album')->assertNotFound();
        $this->get('/albums/missing-album')->assertNotFound();
    }

    public function test_album_detail_only_lists_public_photos_and_counts_public_photos(): void
    {
        $publicPhoto = $this->publicPhoto('公开相册图片');
        $draftPhoto = $this->publicPhoto('草稿相册图片', ['status' => 'draft']);
        $archivedPhoto = $this->publicPhoto('归档相册图片', ['status' => 'archived']);
        $restrictedPhoto = $this->publicPhoto('受限相册图片', ['copyright_status' => 'restricted']);
        $removeRequestedPhoto = $this->publicPhoto('请求下架相册图片', ['copyright_status' => 'remove_requested']);

        $album = $this->publishedAlbum('公开相册', ['slug' => 'public-album']);
        $album->photos()->sync([
            $publicPhoto->id,
            $draftPhoto->id,
            $archivedPhoto->id,
            $restrictedPhoto->id,
            $removeRequestedPhoto->id,
        ]);

        $this->get('/albums/public-album')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Show')
                ->where('albumDetail.album.public_photos_count', 1)
                ->where('albumDetail.photos.meta.total', 1)
                ->has('albumDetail.photos.data', 1)
                ->where('albumDetail.photos.data.0.title', '公开相册图片')
            );
    }

    public function test_album_detail_uses_manual_public_cover_and_falls_back_when_cover_is_not_public(): void
    {
        $manualCover = $this->publicPhoto('手动封面', [
            'display_key' => 'photos/display/manual-detail-cover.webp',
            'published_at' => now()->subDay(),
        ]);
        $otherPhoto = $this->publicPhoto('普通公开图', [
            'display_key' => 'photos/display/other-detail-cover.webp',
            'published_at' => now(),
        ]);
        $manualAlbum = $this->publishedAlbum('手动封面相册', [
            'slug' => 'manual-cover-album',
            'cover_photo_id' => $manualCover->id,
        ]);
        $manualAlbum->photos()->sync([$manualCover->id, $otherPhoto->id]);

        $this->get('/albums/manual-cover-album')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Show')
                ->where('albumDetail.album.cover_image_url', '/storage/photos/display/manual-detail-cover.webp')
            );

        $restrictedCover = $this->publicPhoto('不可公开封面', [
            'copyright_status' => 'restricted',
            'display_key' => 'photos/display/restricted-detail-cover.webp',
        ]);
        $fallbackCover = $this->publicPhoto('兜底封面', [
            'display_key' => 'photos/display/fallback-detail-cover.webp',
            'published_at' => now(),
        ]);
        $fallbackAlbum = $this->publishedAlbum('兜底封面相册', [
            'slug' => 'fallback-cover-album',
            'cover_photo_id' => $restrictedCover->id,
        ]);
        $fallbackAlbum->photos()->sync([$restrictedCover->id, $fallbackCover->id]);

        $this->get('/albums/fallback-cover-album')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Show')
                ->where('albumDetail.album.cover_image_url', '/storage/photos/display/fallback-detail-cover.webp')
            );
    }

    public function test_album_detail_sorts_photos_and_ignores_invalid_sort_values(): void
    {
        $newerEvent = $this->publicPhoto('后发生的事件', [
            'event_date' => '2022-12-18',
            'published_at' => now()->subDays(2),
        ]);
        $olderEvent = $this->publicPhoto('先发生的事件', [
            'event_date' => '2021-07-10',
            'published_at' => now()->subDay(),
        ]);

        $album = $this->publishedAlbum('排序相册', ['slug' => 'sort-album']);
        $album->photos()->sync([$newerEvent->id, $olderEvent->id]);

        $this->get('/albums/sort-album?sort=event_asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Show')
                ->where('albumDetail.filters.sort', 'event_asc')
                ->where('albumDetail.photos.data.0.title', '先发生的事件')
                ->where('albumDetail.photos.data.1.title', '后发生的事件')
            );

        $this->get('/albums/sort-album?sort=unknown-sort')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Show')
                ->where('albumDetail.filters.sort', 'event_desc')
                ->where('albumDetail.photos.data.0.title', '后发生的事件')
                ->where('albumDetail.photos.data.1.title', '先发生的事件')
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
