<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Category;
use App\Models\Comment;
use App\Models\PhotoFavorite;
use App\Models\PhotoLike;
use App\Models\PhotoShare;
use App\Models\Photo;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPhotoGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_photo_gallery_with_default_payload(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        Photo::query()->create([
            'title' => '公开图库图片',
            'status' => 'published',
            'published_at' => now(),
            'display_key' => 'photos/display/public.webp',
        ]);

        $this->get('/photos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Index')
                ->where('gallery.site.name', '梅西影像档案库')
                ->where('gallery.filters.sort', 'published_desc')
                ->has('gallery.filter_options.category_groups', 7)
                ->has('gallery.photos.data', 1)
                ->where('gallery.photos.data.0.title', '公开图库图片')
                ->where('gallery.photos.data.0.image_url', '/storage/photos/display/public.webp')
            );
    }

    public function test_photo_gallery_only_exposes_public_photos(): void
    {
        Photo::query()->create([
            'title' => '公开图片',
            'status' => 'published',
            'published_at' => now(),
        ]);
        Photo::query()->create([
            'title' => '草稿图片',
            'status' => 'draft',
            'published_at' => now(),
        ]);
        Photo::query()->create([
            'title' => '归档图片',
            'status' => 'archived',
            'published_at' => now(),
        ]);
        Photo::query()->create([
            'title' => '受限图片',
            'status' => 'published',
            'copyright_status' => 'restricted',
            'published_at' => now(),
        ]);
        Photo::query()->create([
            'title' => '请求下架图片',
            'status' => 'published',
            'copyright_status' => 'remove_requested',
            'published_at' => now(),
        ]);

        $this->get('/photos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Index')
                ->has('gallery.photos.data', 1)
                ->where('gallery.photos.data.0.title', '公开图片')
            );
    }

    public function test_photo_gallery_filters_by_keyword_category_tag_album_and_event_date(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $competition = Category::query()->roots()->where('slug', 'competition')->firstOrFail();
        $worldCup = Category::query()->children()->where('parent_id', $competition->id)->firstOrFail();
        $tag = Tag::query()->create([
            'name' => '夺冠',
            'type' => '荣誉',
            'sort_order' => 10,
        ]);
        $album = Album::query()->create([
            'title' => '2022 世界杯决赛',
            'slug' => '2022-world-cup-final',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $matching = Photo::query()->create([
            'title' => 'World Cup final celebration',
            'description' => '阿根廷夺冠夜',
            'status' => 'published',
            'event_date' => '2022-12-18',
            'published_at' => now(),
        ]);
        $matching->categories()->sync([$worldCup->id]);
        $matching->tags()->sync([$tag->id]);
        $matching->albums()->sync([$album->id]);

        $other = Photo::query()->create([
            'title' => '训练图片',
            'status' => 'published',
            'event_date' => '2023-01-01',
            'published_at' => now(),
        ]);
        $other->categories()->sync([$worldCup->id]);
        $other->tags()->sync([$tag->id]);
        $other->albums()->sync([$album->id]);

        $query = http_build_query([
            'q' => 'World Cup',
            'categories' => ['competition' => $worldCup->id],
            'tags' => [$tag->id],
            'album_id' => $album->id,
            'date_from' => '2022-12-18',
            'date_to' => '2022-12-18',
        ]);

        $this->get('/photos?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Index')
                ->where('gallery.filters.q', 'World Cup')
                ->where('gallery.filters.categories.competition', $worldCup->id)
                ->where('gallery.filters.tags.0', $tag->id)
                ->where('gallery.filters.album_id', $album->id)
                ->where('gallery.filters.date_from', '2022-12-18')
                ->where('gallery.filters.date_to', '2022-12-18')
                ->has('gallery.photos.data', 1)
                ->where('gallery.photos.data.0.title', 'World Cup final celebration')
            );
    }


    public function test_photo_gallery_filters_by_p1_15_advanced_metadata_and_sorts_by_hot_score(): void
    {
        $source = Source::query()->create([
            'original_url' => 'https://example.com/official-gallery',
            'is_enabled' => true,
        ]);
        $peopleTag = Tag::query()->create([
            'name' => '队友同框',
            'type' => '人物关系',
        ]);
        $user = User::factory()->create();

        $matching = Photo::query()->create([
            'title' => '高级筛选命中图片',
            'status' => 'published',
            'source_id' => $source->id,
            'copyright_status' => 'credited',
            'watermark_status' => 'present',
            'width' => 3200,
            'height' => 1800,
            'published_at' => now()->subDay(),
        ]);
        $matching->tags()->sync([$peopleTag->id]);

        $sameFiltersLowerScore = Photo::query()->create([
            'title' => '同条件低热度图片',
            'status' => 'published',
            'source_id' => $source->id,
            'copyright_status' => 'credited',
            'watermark_status' => 'present',
            'width' => 3000,
            'height' => 2000,
            'published_at' => now(),
        ]);
        $sameFiltersLowerScore->tags()->sync([$peopleTag->id]);

        Photo::query()->create([
            'title' => '竖图不应命中',
            'status' => 'published',
            'source_id' => $source->id,
            'copyright_status' => 'credited',
            'watermark_status' => 'present',
            'width' => 1200,
            'height' => 2000,
            'published_at' => now(),
        ]);

        PhotoFavorite::query()->create(['photo_id' => $matching->id, 'user_id' => $user->id]);
        PhotoLike::query()->create(['photo_id' => $matching->id, 'user_id' => $user->id]);
        Comment::query()->create([
            'photo_id' => $matching->id,
            'user_id' => $user->id,
            'type' => 'discussion',
            'content' => '公开评论',
            'status' => 'published',
        ]);
        PhotoShare::query()->create([
            'photo_id' => $matching->id,
            'user_id' => $user->id,
            'channel' => 'copy_link',
            'page_url' => 'http://127.0.0.1/photos/'.$matching->uuid,
        ]);

        $query = http_build_query([
            'source_id' => $source->id,
            'copyright_status' => 'credited',
            'orientation' => 'landscape',
            'resolution' => 'ultra',
            'watermark_status' => 'present',
            'people_tags' => [$peopleTag->id],
            'sort' => 'hot_desc',
        ]);

        $this->get('/photos?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Index')
                ->where('gallery.filters.source_mode', 'specific')
                ->where('gallery.filters.source_id', $source->id)
                ->where('gallery.filters.copyright_status', 'credited')
                ->where('gallery.filters.orientation', 'landscape')
                ->where('gallery.filters.resolution', 'ultra')
                ->where('gallery.filters.watermark_status', 'present')
                ->where('gallery.filters.people_tags.0', $peopleTag->id)
                ->where('gallery.filters.sort', 'hot_desc')
                ->has('gallery.filter_options.sources', 1)
                ->has('gallery.filter_options.people_tags', 1)
                ->has('gallery.photos.data', 2)
                ->where('gallery.photos.data.0.title', '高级筛选命中图片')
                ->where('gallery.photos.data.0.resolution_label', '超清')
                ->where('gallery.photos.data.0.orientation_label', '横图')
                ->where('gallery.photos.data.0.watermark_status_label', '有水印')
                ->where('gallery.photos.data.1.title', '同条件低热度图片')
            );
    }


    public function test_photo_gallery_sorts_by_event_date(): void
    {
        Photo::query()->create([
            'title' => '后发生的事件',
            'status' => 'published',
            'event_date' => '2022-12-18',
            'published_at' => now()->subDays(2),
        ]);
        Photo::query()->create([
            'title' => '先发生的事件',
            'status' => 'published',
            'event_date' => '2021-07-10',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/photos?sort=event_asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Index')
                ->where('gallery.filters.sort', 'event_asc')
                ->has('gallery.photos.data', 2)
                ->where('gallery.photos.data.0.title', '先发生的事件')
                ->where('gallery.photos.data.1.title', '后发生的事件')
            );
    }

    public function test_photo_gallery_ignores_invalid_filter_values_without_error(): void
    {
        Photo::query()->create([
            'title' => '稳定公开图片',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $query = http_build_query([
            'categories' => ['competition' => 99999],
            'tags' => [99999],
            'album_id' => 99999,
            'date_from' => 'not-a-date',
            'date_to' => 'also-not-a-date',
            'sort' => 'unknown-sort',
        ]);

        $this->get('/photos?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Index')
                ->where('gallery.filters.categories', [])
                ->where('gallery.filters.tags', [])
                ->where('gallery.filters.album_id', null)
                ->where('gallery.filters.date_from', null)
                ->where('gallery.filters.date_to', null)
                ->where('gallery.filters.sort', 'published_desc')
                ->has('gallery.photos.data', 1)
                ->where('gallery.photos.data.0.title', '稳定公开图片')
            );
    }

    public function test_photo_gallery_keeps_card_payload_minimal_and_filters_album_options(): void
    {
        $source = Source::query()->create([
            'original_url' => 'https://example.com/source',
            'copyright_note' => '公开来源说明',
            'internal_note' => '后台内部备注',
        ]);
        $publicAlbum = Album::query()->create([
            'title' => '公开有效相册',
            'slug' => 'public-album',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $emptyAlbum = Album::query()->create([
            'title' => '空相册',
            'slug' => 'empty-album',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $hiddenAlbum = Album::query()->create([
            'title' => '隐藏相册',
            'slug' => 'hidden-album',
            'status' => 'hidden',
            'published_at' => now(),
        ]);
        $restrictedAlbum = Album::query()->create([
            'title' => '只有受限图片的相册',
            'slug' => 'restricted-album',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $publicPhoto = Photo::query()->create([
            'title' => '公开图片',
            'status' => 'published',
            'source_id' => $source->id,
            'original_key' => 'photos/original/private.jpg',
            'stored_filename' => 'system-private.webp',
            'published_at' => now(),
        ]);
        $restrictedPhoto = Photo::query()->create([
            'title' => '受限相册图片',
            'status' => 'published',
            'copyright_status' => 'restricted',
            'published_at' => now(),
        ]);
        $publicAlbum->photos()->sync([$publicPhoto->id]);
        $restrictedAlbum->photos()->sync([$restrictedPhoto->id]);

        $this->get('/photos?album_id='.$emptyAlbum->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Index')
                ->where('gallery.filters.album_id', null)
                ->has('gallery.filter_options.albums', 1)
                ->where('gallery.filter_options.albums.0.id', $publicAlbum->id)
                ->where('gallery.filter_options.albums.0.title', '公开有效相册')
                ->has('gallery.photos.data', 1)
                ->where('gallery.photos.data.0.title', '公开图片')
                ->missing('gallery.photos.data.0.source')
                ->missing('gallery.photos.data.0.original_key')
                ->missing('gallery.photos.data.0.stored_filename')
                ->missing('gallery.photos.data.0.photo_upload_batch_id')
                ->missing('gallery.photos.data.0.uploaded_by')
            );
    }
}
