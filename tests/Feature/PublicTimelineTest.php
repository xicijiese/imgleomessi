<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\PhotoFavorite;
use App\Models\PhotoLike;
use App\Models\PhotoShare;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_timeline_index_with_year_groups(): void
    {
        $public2022 = $this->publicPhoto('2022 世界杯决赛', [
            'event_date' => '2022-12-18',
            'display_key' => 'photos/display/2022-final.webp',
        ]);
        $public2021 = $this->publicPhoto('2021 美洲杯', [
            'event_date' => '2021-07-10',
        ]);
        $this->publicPhoto('缺少事件日期不进入时间线', [
            'event_date' => null,
        ]);
        $this->publicPhoto('草稿不进入时间线', [
            'status' => 'draft',
            'event_date' => '2022-12-18',
        ]);
        $this->publicPhoto('受限不进入时间线', [
            'copyright_status' => 'restricted',
            'event_date' => '2022-12-18',
        ]);

        $album = $this->publishedAlbum('世界杯公开相册');
        $album->photos()->sync([$public2022->id]);

        $this->get('/timeline')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Timeline/Index')
                ->where('timeline.site.name', '梅西影像档案库')
                ->where('timeline.seo.title', '时间线')
                ->where('timeline.scope.type', 'index')
                ->where('timeline.summary.years_count', 2)
                ->where('timeline.summary.photos_count', 2)
                ->where('timeline.summary.albums_count', 1)
                ->where('timeline.navigation.4.label', '时间线')
                ->has('timeline.years', 2)
                ->where('timeline.years.0.year', 2022)
                ->where('timeline.years.0.photos_count', 1)
                ->where('timeline.years.0.albums_count', 1)
                ->where('timeline.years.0.cover_image_url', '/storage/photos/display/2022-final.webp')
                ->where('timeline.years.1.year', 2021)
                ->has('timeline.latest_photos', 2)
                ->where('timeline.latest_photos.0.title', '2022 世界杯决赛')
                ->where('timeline.latest_photos.1.title', '2021 美洲杯')
            );

        $this->assertNotNull($public2021->uuid);
    }

    public function test_year_archive_filters_public_dated_photos_and_related_albums(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $competition = Category::query()->roots()->where('slug', 'competition')->firstOrFail();
        $worldCup = Category::query()->children()->where('parent_id', $competition->id)->firstOrFail();
        $tag = Tag::query()->create([
            'name' => '夺冠',
            'sort_order' => 10,
        ]);

        $matching = $this->publicPhoto('World Cup final celebration', [
            'description' => '阿根廷夺冠夜',
            'event_date' => '2022-12-18',
            'display_key' => 'photos/display/world-cup-final.webp',
        ]);
        $matching->categories()->sync([$worldCup->id]);
        $matching->tags()->sync([$tag->id]);

        $wrongKeyword = $this->publicPhoto('2022 训练图', [
            'event_date' => '2022-12-19',
        ]);
        $wrongKeyword->categories()->sync([$worldCup->id]);
        $wrongKeyword->tags()->sync([$tag->id]);

        $wrongYear = $this->publicPhoto('World Cup later year', [
            'event_date' => '2023-01-01',
        ]);
        $wrongYear->categories()->sync([$worldCup->id]);
        $wrongYear->tags()->sync([$tag->id]);

        $album = $this->publishedAlbum('World Cup final album', [
            'slug' => 'world-cup-final-album',
            'cover_photo_id' => $matching->id,
        ]);
        $album->photos()->sync([$matching->id]);
        $album->categories()->sync([$worldCup->id]);

        $query = http_build_query([
            'q' => 'World Cup',
            'categories' => ['competition' => $worldCup->id],
            'tags' => [$tag->id],
        ]);

        $this->get('/timeline/2022?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Timeline/Archive')
                ->where('timeline.scope.type', 'year')
                ->where('timeline.scope.year', 2022)
                ->where('timeline.filters.q', 'World Cup')
                ->where('timeline.filters.categories.competition', $worldCup->id)
                ->where('timeline.filters.tags.0', $tag->id)
                ->where('timeline.filter_options.tags.0', ['id' => $tag->id, 'name' => '夺冠'])
                ->where('timeline.summary.photos_count', 1)
                ->where('timeline.summary.albums_count', 1)
                ->has('timeline.months', 1)
                ->where('timeline.months.0.month', 12)
                ->has('timeline.photos.data', 1)
                ->where('timeline.photos.data.0.title', 'World Cup final celebration')
                ->where('timeline.photos.data.0.image_url', '/storage/photos/display/world-cup-final.webp')
                ->has('timeline.albums', 1)
                ->where('timeline.albums.0.title', 'World Cup final album')
                ->where('timeline.albums.0.cover_image_url', '/storage/photos/display/world-cup-final.webp')
            );
    }

    public function test_month_archive_sorts_by_event_date_and_keeps_empty_state_stable(): void
    {
        $later = $this->publicPhoto('月底事件', [
            'event_date' => '2022-12-18',
            'published_at' => now()->subDays(2),
        ]);
        $earlier = $this->publicPhoto('月初事件', [
            'event_date' => '2022-12-01',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/timeline/2022/12?sort=event_asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Timeline/Archive')
                ->where('timeline.scope.type', 'month')
                ->where('timeline.scope.year', 2022)
                ->where('timeline.scope.month', 12)
                ->where('timeline.filters.sort', 'event_asc')
                ->where('timeline.summary.photos_count', 2)
                ->where('timeline.photos.data.0.title', '月初事件')
                ->where('timeline.photos.data.1.title', '月底事件')
            );

        $this->get('/timeline/1999')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Timeline/Archive')
                ->where('timeline.scope.type', 'year')
                ->where('timeline.scope.year', 1999)
                ->where('timeline.summary.photos_count', 0)
                ->has('timeline.months', 0)
                ->has('timeline.photos.data', 0)
                ->has('timeline.albums', 0)
            );

        $this->assertNotNull($later->uuid);
        $this->assertNotNull($earlier->uuid);
    }

    public function test_timeline_ignores_invalid_filter_values_and_rejects_invalid_dates(): void
    {
        $this->publicPhoto('稳定时间线图片', [
            'event_date' => '2022-12-18',
        ]);

        $query = http_build_query([
            'categories' => ['competition' => 99999],
            'tags' => [99999],
            'sort' => 'unknown-sort',
        ]);

        $this->get('/timeline/2022?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Timeline/Archive')
                ->where('timeline.filters.categories', [])
                ->where('timeline.filters.tags', [])
                ->where('timeline.filters.sort', 'event_desc')
                ->has('timeline.photos.data', 1)
                ->where('timeline.photos.data.0.title', '稳定时间线图片')
            );

        $this->get('/timeline/not-a-year')->assertNotFound();
        $this->get('/timeline/1899')->assertNotFound();
        $this->get('/timeline/2022/13')->assertNotFound();
    }

    public function test_timeline_hot_sort_uses_public_interaction_counts(): void
    {
        $user = User::factory()->create();
        $hotter = $this->publicPhoto('热度更高的时间线图片', [
            'event_date' => '2022-12-18',
            'published_at' => now()->subDay(),
        ]);
        $colder = $this->publicPhoto('热度较低的时间线图片', [
            'event_date' => '2022-12-19',
            'published_at' => now(),
        ]);

        PhotoFavorite::query()->create(['photo_id' => $hotter->id, 'user_id' => $user->id]);
        PhotoLike::query()->create(['photo_id' => $hotter->id, 'user_id' => $user->id]);
        PhotoShare::query()->create([
            'photo_id' => $hotter->id,
            'user_id' => $user->id,
            'channel' => 'copy_link',
            'page_url' => 'http://127.0.0.1/photos/'.$hotter->uuid,
        ]);
        Comment::query()->create([
            'photo_id' => $hotter->id,
            'user_id' => $user->id,
            'type' => 'discussion',
            'content' => '公开评论',
            'status' => 'published',
        ]);

        $this->get('/timeline/2022?sort=hot_desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Timeline/Archive')
                ->where('timeline.filters.sort', 'hot_desc')
                ->where('timeline.photos.data.0.title', '热度更高的时间线图片')
                ->where('timeline.photos.data.1.title', '热度较低的时间线图片')
            );

        $this->assertNotNull($colder->uuid);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publicPhoto(string $title, array $attributes = []): Photo
    {
        return Photo::query()->create(array_merge([
            'title' => $title,
            'status' => 'published',
            'copyright_status' => 'unknown',
            'event_date' => '2022-12-18',
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
}
