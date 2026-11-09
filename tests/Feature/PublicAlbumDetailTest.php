<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\PhotoSimilarityCandidate;
use App\Models\Tag;
use App\Models\User;
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
            'sort_order' => 10,
        ]);
        $photo = $this->publicPhoto('世界杯决赛捧杯', [
            'display_key' => 'photos/display/world-cup-final.webp',
            'thumbnail_key' => 'photos/thumbnail/world-cup-final.webp',
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
                ->where('albumDetail.album.cover_image_url', '/storage/photos/thumbnail/world-cup-final.webp')
                ->has('albumDetail.album.categories', 8)
                ->has('albumDetail.photos.data', 1)
                ->where('albumDetail.photos.data.0.title', '世界杯决赛捧杯')
                ->where('albumDetail.photos.data.0.url', '/photos/'.$photo->uuid.'?album=2022-world-cup-final')
                ->where('albumDetail.photos.data.0.image_url', '/storage/photos/thumbnail/world-cup-final.webp')
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
            'thumbnail_key' => 'photos/thumbnail/manual-detail-cover.webp',
            'published_at' => now()->subDay(),
        ]);
        $otherPhoto = $this->publicPhoto('普通公开图', [
            'display_key' => 'photos/display/other-detail-cover.webp',
            'thumbnail_key' => 'photos/thumbnail/other-detail-cover.webp',
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
                ->where('albumDetail.album.cover_image_url', '/storage/photos/thumbnail/manual-detail-cover.webp')
            );

        $restrictedCover = $this->publicPhoto('不可公开封面', [
            'copyright_status' => 'restricted',
            'display_key' => 'photos/display/restricted-detail-cover.webp',
            'thumbnail_key' => 'photos/thumbnail/restricted-detail-cover.webp',
        ]);
        $fallbackCover = $this->publicPhoto('兜底封面', [
            'display_key' => 'photos/display/fallback-detail-cover.webp',
            'thumbnail_key' => 'photos/thumbnail/fallback-detail-cover.webp',
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
                ->where('albumDetail.album.cover_image_url', '/storage/photos/thumbnail/fallback-detail-cover.webp')
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

    public function test_photo_pagination_keeps_sort_and_album_context(): void
    {
        $album = $this->publishedAlbum('分页相册', ['slug' => 'paginated-album']);
        $ids = [];
        for ($i = 1; $i <= 25; $i++) {
            $ids[] = $this->publicPhoto('分页照片 '.$i, ['event_date' => sprintf('2022-01-%02d', $i)])->id;
        }
        $album->photos()->sync($ids);
        $this->get('/albums/paginated-album?sort=event_asc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('albumDetail.photos.data', 24)
            ->where('albumDetail.photos.links.next', function ($url) {
                parse_str(parse_url($url, PHP_URL_QUERY), $params);

                return $params['sort'] === 'event_asc' && $params['page'] === '2';
            }));
        $this->get('/albums/paginated-album?sort=event_asc&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('albumDetail.photos.data', 1)
            ->where('albumDetail.photos.meta.current_page', 2)
            ->where('albumDetail.photos.data.0.title', '分页照片 25')
            ->where('albumDetail.photos.data.0.url', fn ($url) => str_contains($url, 'album=paginated-album'))
            ->where('albumDetail.photos.links.next', null));
    }

    public function test_album_slides_keep_current_photo_comments_and_recommendation_links_in_sync(): void
    {
        $album = $this->publishedAlbum('幻灯片相册', ['slug' => 'slides']);
        $first = $this->publicPhoto('第一张', ['event_date' => '2022-01-01', 'display_key' => 'photos/display/first.webp', 'thumbnail_key' => 'photos/thumbnail/first.webp']);
        $second = $this->publicPhoto('第二张', ['event_date' => '2022-01-02', 'display_key' => 'photos/display/second.webp']);
        $private = $this->publicPhoto('不可见图片', ['status' => 'draft']);
        $album->photos()->sync([$first->id, $second->id, $private->id]);
        $user = User::factory()->create();
        foreach ([$first, $second] as $photo) {
            Comment::query()->create(['photo_id' => $photo->id, 'user_id' => $user->id, 'type' => 'discussion', 'status' => 'published', 'content' => $photo->title.'的评论']);
        }
        PhotoSimilarityCandidate::query()->create(['photo_id' => $first->id, 'candidate_photo_id' => $second->id, 'distance' => 1, 'similarity_score' => 98.4, 'status' => 'pending_review']);

        $this->actingAs($user)->get('/albums/slides?sort=event_asc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('albumDetail.viewer.position', 1)
            ->where('albumDetail.viewer.total', 2)
            ->where('albumDetail.viewer.previous_url', null)
            ->where('albumDetail.viewer.next_url', '/albums/slides?sort=event_asc&slide=2')
            ->where('albumDetail.current_photo.photo.uuid', $first->uuid)
            ->where('albumDetail.current_photo.photo.image_url', '/storage/photos/display/first.webp')
            ->where('albumDetail.current_photo.comments.can_submit', true)
            ->where('albumDetail.current_photo.comments.data.0.content', '第一张的评论')
            ->where('albumDetail.current_photo.similar.data.0.url', '/photos/'.$second->uuid));

        $this->get('/albums/slides?sort=event_asc&slide=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('albumDetail.viewer.position', 2)
            ->where('albumDetail.viewer.next_url', null)
            ->where('albumDetail.current_photo.photo.uuid', $second->uuid)
            ->where('albumDetail.current_photo.comments.data.0.content', '第二张的评论'));
    }

    public function test_album_slide_can_cross_thumbnail_pages_and_clamps_out_of_range_positions(): void
    {
        $album = $this->publishedAlbum('长相册', ['slug' => 'long-slides']);
        for ($i = 1; $i <= 25; $i++) {
            $photo = $this->publicPhoto('照片'.$i, ['event_date' => sprintf('2022-01-%02d', $i)]);
            $album->photos()->attach($photo);
        }
        $this->get('/albums/long-slides?sort=event_asc&slide=25')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('albumDetail.photos.meta.current_page', 2)
            ->has('albumDetail.photos.data', 1)
            ->where('albumDetail.current_photo.photo.title', '照片25')
            ->where('albumDetail.viewer.previous_url', '/albums/long-slides?sort=event_asc&slide=24')
            ->where('albumDetail.viewer.next_url', null));
        $this->get('/albums/long-slides?sort=event_asc&slide=999')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('albumDetail.viewer.position', 25));
        $this->get('/albums/long-slides?sort=event_asc&slide=-1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('albumDetail.viewer.position', 1));
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
