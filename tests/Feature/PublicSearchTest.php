<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Source;
use App\Models\Tag;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_search_with_default_payload(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        Photo::query()->create([
            'title' => '公开图库图片',
            'status' => 'published',
            'published_at' => now(),
            'display_key' => 'photos/display/public.webp',
        ]);

        $this->get('/search')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('search.site.name', '梅西影像档案库')
                ->where('search.filters.sort', 'published_desc')
                ->has('search.filter_options.category_groups', 7)
                ->has('search.photos.data', 1)
                ->where('search.photos.data.0.title', '公开图库图片')
                ->where('search.photos.data.0.image_url', '/storage/photos/display/public.webp')
            );
    }

    public function test_search_only_exposes_public_photos(): void
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

        $this->get('/search')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->has('search.photos.data', 1)
                ->where('search.photos.data.0.title', '公开图片')
            );
    }

    public function test_search_filters_by_keyword_category_tag_album_and_event_date(): void
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

        $this->get('/search?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('search.filters.q', 'World Cup')
                ->where('search.filters.categories.competition', $worldCup->id)
                ->where('search.filters.tags.0', $tag->id)
                ->where('search.filters.album_id', $album->id)
                ->where('search.filters.date_from', '2022-12-18')
                ->where('search.filters.date_to', '2022-12-18')
                ->has('search.photos.data', 1)
                ->where('search.photos.data.0.title', 'World Cup final celebration')
            );
    }


    public function test_search_reuses_p1_15_advanced_metadata_filters(): void
    {
        $source = Source::query()->create([
            'original_url' => 'https://example.com/search-source',
            'is_enabled' => true,
        ]);
        $peopleTag = Tag::query()->create([
            'name' => '家人同框',
            'type' => '人物关系',
        ]);

        $matching = Photo::query()->create([
            'title' => '搜索高级筛选命中图片',
            'status' => 'published',
            'source_id' => $source->id,
            'copyright_status' => 'credited',
            'watermark_status' => 'none',
            'width' => 1200,
            'height' => 1800,
            'published_at' => now(),
        ]);
        $matching->tags()->sync([$peopleTag->id]);

        $other = Photo::query()->create([
            'title' => '水印状态不同不应命中',
            'status' => 'published',
            'source_id' => $source->id,
            'copyright_status' => 'credited',
            'watermark_status' => 'present',
            'width' => 1200,
            'height' => 1800,
            'published_at' => now(),
        ]);
        $other->tags()->sync([$peopleTag->id]);

        $query = http_build_query([
            'source_mode' => 'has',
            'copyright_status' => 'credited',
            'orientation' => 'portrait',
            'resolution' => 'standard',
            'watermark_status' => 'none',
            'people_tags' => [$peopleTag->id],
        ]);

        $this->get('/search?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('search.filters.source_mode', 'has')
                ->where('search.filters.source_id', null)
                ->where('search.filters.copyright_status', 'credited')
                ->where('search.filters.orientation', 'portrait')
                ->where('search.filters.resolution', 'standard')
                ->where('search.filters.watermark_status', 'none')
                ->where('search.filters.people_tags.0', $peopleTag->id)
                ->has('search.filter_options.people_tags', 1)
                ->has('search.photos.data', 1)
                ->where('search.photos.data.0.title', '搜索高级筛选命中图片')
                ->where('search.photos.data.0.resolution_label', '普通')
                ->where('search.photos.data.0.orientation_label', '竖图')
                ->where('search.photos.data.0.watermark_status_label', '无水印')
            );
    }
    public function test_search_does_not_match_original_or_stored_file_names(): void
    {
        Photo::query()->create([
            'title' => '标题命中 worldcup',
            'original_filename' => 'private-source.jpg',
            'stored_filename' => 'private-display.webp',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        Photo::query()->create([
            'title' => '原始文件名不应命中',
            'original_filename' => 'messi-worldcup-source.jpg',
            'status' => 'published',
            'published_at' => now(),
        ]);
        Photo::query()->create([
            'title' => '系统文件名不应命中',
            'stored_filename' => 'archive-worldcup-display.webp',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get('/search?q=worldcup')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('search.filters.q', 'worldcup')
                ->has('search.photos.data', 1)
                ->where('search.photos.data.0.title', '标题命中 worldcup')
                ->missing('search.photos.data.0.source')
                ->missing('search.photos.data.0.original_key')
                ->missing('search.photos.data.0.stored_filename')
                ->missing('search.photos.data.0.photo_upload_batch_id')
                ->missing('search.photos.data.0.uploaded_by')
            );
    }

    public function test_search_sorts_by_event_date(): void
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

        $this->get('/search?sort=event_asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('search.filters.sort', 'event_asc')
                ->has('search.photos.data', 2)
                ->where('search.photos.data.0.title', '先发生的事件')
                ->where('search.photos.data.1.title', '后发生的事件')
            );
    }

    public function test_search_ignores_invalid_filter_values_without_error(): void
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

        $this->get('/search?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('search.filters.categories', [])
                ->where('search.filters.tags', [])
                ->where('search.filters.album_id', null)
                ->where('search.filters.date_from', null)
                ->where('search.filters.date_to', null)
                ->where('search.filters.sort', 'published_desc')
                ->has('search.photos.data', 1)
                ->where('search.photos.data.0.title', '稳定公开图片')
            );
    }
}
