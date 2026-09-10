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

class PublicDimensionArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_teams_index_with_public_career_stage_cards(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $argentina = $this->childCategory('career-stage', 'argentina-era', '阿根廷国家队');
        $barcelona = $this->childCategory('career-stage', 'barcelona-era', '巴萨时期');
        $hidden = $this->childCategory('career-stage', 'hidden-stage', '隐藏阶段', ['visibility' => 'hidden']);

        $publicPhoto = $this->publicPhoto('阿根廷公开图片', [
            'event_date' => '2022-12-18',
            'display_key' => 'photos/display/argentina.webp',
        ]);
        $publicPhoto->categories()->sync([$argentina->id]);

        $draftPhoto = $this->publicPhoto('巴萨草稿图片', ['status' => 'draft']);
        $draftPhoto->categories()->sync([$barcelona->id]);

        $hiddenPhoto = $this->publicPhoto('隐藏阶段图片');
        $hiddenPhoto->categories()->sync([$hidden->id]);

        $album = $this->publishedAlbum('阿根廷公开相册', ['cover_photo_id' => $publicPhoto->id]);
        $album->photos()->sync([$publicPhoto->id]);

        $this->get('/teams')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dimensions/Index')
                ->where('archive.dimension.type', 'teams')
                ->where('archive.dimension.label', '球队 / 阶段')
                ->where('archive.dimension.path', '/teams')
                ->where('archive.summary.dimensions_count', 1)
                ->where('archive.summary.photos_count', 1)
                ->where('archive.summary.albums_count', 1)
                ->has('archive.dimensions', 1)
                ->where('archive.dimensions.0.name', '阿根廷国家队')
                ->where('archive.dimensions.0.url', '/teams/argentina-era')
                ->where('archive.dimensions.0.cover_image_url', '/storage/photos/display/argentina.webp')
                ->where('archive.dimensions.0.public_photos_count', 1)
                ->where('archive.dimensions.0.public_albums_count', 1)
            );

        $this->assertNotNull($draftPhoto->uuid);
        $this->assertNotNull($hiddenPhoto->uuid);
    }

    public function test_team_detail_filters_public_photos_albums_tags_people_and_years(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $argentina = $this->childCategory('career-stage', 'argentina-era', '阿根廷国家队');
        $honorTag = Tag::query()->create(['name' => '夺冠', 'sort_order' => 10]);
        $peopleTag = Tag::query()->create(['name' => '队友同框', 'sort_order' => 20]);
        $user = User::factory()->create();

        $matching = $this->publicPhoto('World Cup final celebration', [
            'description' => '阿根廷夺冠夜',
            'event_date' => '2022-12-18',
            'display_key' => 'photos/display/world-cup-final.webp',
            'published_at' => now()->subDay(),
        ]);
        $matching->categories()->sync([$argentina->id]);
        $matching->tags()->sync([$honorTag->id, $peopleTag->id]);

        $wrongKeyword = $this->publicPhoto('训练图片', ['event_date' => '2022-12-19']);
        $wrongKeyword->categories()->sync([$argentina->id]);
        $wrongKeyword->tags()->sync([$honorTag->id, $peopleTag->id]);

        $wrongYear = $this->publicPhoto('World Cup later year', ['event_date' => '2023-01-01']);
        $wrongYear->categories()->sync([$argentina->id]);
        $wrongYear->tags()->sync([$honorTag->id, $peopleTag->id]);

        $restricted = $this->publicPhoto('World Cup restricted', ['copyright_status' => 'restricted', 'event_date' => '2022-12-18']);
        $restricted->categories()->sync([$argentina->id]);
        $restricted->tags()->sync([$honorTag->id, $peopleTag->id]);

        $album = $this->publishedAlbum('阿根廷公开相册', ['slug' => 'argentina-public-album', 'cover_photo_id' => $matching->id]);
        $album->photos()->sync([$matching->id, $restricted->id]);
        $album->categories()->sync([$argentina->id]);

        PhotoFavorite::query()->create(['photo_id' => $matching->id, 'user_id' => $user->id]);
        PhotoLike::query()->create(['photo_id' => $matching->id, 'user_id' => $user->id]);
        PhotoShare::query()->create([
            'photo_id' => $matching->id,
            'user_id' => $user->id,
            'channel' => 'copy_link',
            'page_url' => 'http://127.0.0.1/photos/'.$matching->uuid,
        ]);
        Comment::query()->create([
            'photo_id' => $matching->id,
            'user_id' => $user->id,
            'type' => 'discussion',
            'content' => '公开评论',
            'status' => 'published',
        ]);

        $query = http_build_query([
            'q' => 'World Cup',
            'years' => [2022],
            'tags' => [$honorTag->id],
            'sort' => 'hot_desc',
        ]);

        $this->get('/teams/argentina-era?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dimensions/Show')
                ->where('archive.dimension.type', 'teams')
                ->where('archive.dimension.index_url', '/teams')
                ->where('archive.category.name', '阿根廷国家队')
                ->where('archive.filters.q', 'World Cup')
                ->where('archive.filters.years.0', 2022)
                ->where('archive.filters.tags.0', $honorTag->id)
                ->where('archive.filters.sort', 'hot_desc')
                ->where('archive.summary.photos_count', 1)
                ->where('archive.summary.albums_count', 1)
                ->where('archive.summary.years_count', 2)
                ->has('archive.photos.data', 1)
                ->where('archive.photos.data.0.title', 'World Cup final celebration')
                ->where('archive.photos.data.0.image_url', '/storage/photos/display/world-cup-final.webp')
                ->missing('archive.photos.data.0.original_key')
                ->missing('archive.photos.data.0.stored_filename')
                ->has('archive.albums', 1)
                ->where('archive.albums.0.title', '阿根廷公开相册')
                ->where('archive.albums.0.public_photos_count', 1)
            );

        $this->assertNotNull($wrongKeyword->uuid);
        $this->assertNotNull($wrongYear->uuid);
    }

    public function test_seasons_index_and_detail_use_season_root_only(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $season = $this->childCategory('season', 'season-2022-2023', '2022-2023');
        $career = $this->childCategory('career-stage', 'argentina-era', '阿根廷国家队');
        $hiddenSeason = $this->childCategory('season', 'hidden-season', '隐藏赛季', ['visibility' => 'hidden']);

        $photo = $this->publicPhoto('赛季公开图片', ['event_date' => '2023-05-01']);
        $photo->categories()->sync([$season->id]);

        $this->get('/seasons')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dimensions/Index')
                ->where('archive.dimension.type', 'seasons')
                ->where('archive.dimension.label', '赛季')
                ->where('archive.summary.dimensions_count', 1)
                ->where('archive.dimensions.0.name', '2022-2023')
                ->where('archive.dimensions.0.url', '/seasons/season-2022-2023')
            );

        $this->get('/seasons/season-2022-2023')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dimensions/Show')
                ->where('archive.dimension.type', 'seasons')
                ->where('archive.category.name', '2022-2023')
                ->where('archive.summary.photos_count', 1)
                ->where('archive.photos.data.0.title', '赛季公开图片')
            );

        $this->get('/teams/season-2022-2023')->assertNotFound();
        $this->get('/seasons/argentina-era')->assertNotFound();
        $this->get('/seasons/hidden-season')->assertNotFound();

        $this->assertNotNull($career->id);
        $this->assertNotNull($hiddenSeason->id);
    }

    public function test_dimension_pages_keep_empty_states_stable(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $season = $this->childCategory('season', 'season-2024-2025', '2024-2025');

        $this->get('/teams')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dimensions/Index')
                ->where('archive.summary.dimensions_count', 0)
                ->has('archive.dimensions', 0)
            );

        $this->get('/seasons/season-2024-2025')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dimensions/Show')
                ->where('archive.category.name', '2024-2025')
                ->where('archive.summary.photos_count', 0)
                ->where('archive.summary.albums_count', 0)
                ->has('archive.photos.data', 0)
                ->has('archive.albums', 0)
            );

        $this->assertNotNull($season->id);
    }

    public function test_sitemap_lists_dimension_pages_with_public_content(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $career = $this->childCategory('career-stage', 'argentina-era', '阿根廷国家队');
        $season = $this->childCategory('season', 'season-2022-2023', '2022-2023');
        $emptySeason = $this->childCategory('season', 'season-empty', '空赛季');
        $hiddenCareer = $this->childCategory('career-stage', 'hidden-stage', '隐藏阶段', ['visibility' => 'hidden']);

        $careerPhoto = $this->publicPhoto('阿根廷 sitemap 图片');
        $careerPhoto->categories()->sync([$career->id, $season->id]);

        $hiddenPhoto = $this->publicPhoto('隐藏 sitemap 图片');
        $hiddenPhoto->categories()->sync([$hiddenCareer->id]);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('http://localhost/teams', $content);
        $this->assertStringContainsString('http://localhost/seasons', $content);
        $this->assertStringContainsString('http://localhost/teams/argentina-era', $content);
        $this->assertStringContainsString('http://localhost/seasons/season-2022-2023', $content);
        $this->assertStringNotContainsString('http://localhost/seasons/season-empty', $content);
        $this->assertStringNotContainsString('http://localhost/teams/hidden-stage', $content);

        $this->assertNotNull($emptySeason->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function childCategory(string $rootSlug, string $slug, string $name, array $attributes = []): Category
    {
        $root = Category::query()->roots()->where('slug', $rootSlug)->firstOrFail();

        return Category::query()->create(array_merge([
            'parent_id' => $root->id,
            'name' => $name,
            'slug' => $slug,
            'description' => $name.'相关资料。',
            'sort_order' => 10,
            'visibility' => 'public',
            'is_system' => false,
        ], $attributes));
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
