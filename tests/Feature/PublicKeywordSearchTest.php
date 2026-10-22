<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicKeywordSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_and_gallery_match_a_published_album_title_without_duplicate_photos(): void
    {
        $photo = $this->photo();
        foreach (['世界杯决赛现场', '世界杯夺冠庆祝'] as $title) {
            $album = $this->album($title);
            $album->photos()->attach($photo);
        }
        $draft = $this->photo(['status' => 'draft']);
        $restricted = $this->photo(['copyright_status' => 'restricted']);
        $album->photos()->attach([$draft->id, $restricted->id]);

        foreach (['search', 'photos'] as $route) {
            $payload = $route === 'search' ? 'search' : 'gallery';
            $this->get('/'.$route.'?'.http_build_query(['q' => '世界杯']))->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has($payload.'.photos.data', 1)
                    ->where($payload.'.photos.data.0.uuid', $photo->uuid));
        }
        $this->assertDatabaseHas('search_queries', ['keyword' => '世界杯', 'result_count' => 1]);
    }

    public function test_keyword_matches_photo_and_published_album_season_categories(): void
    {
        $season = $this->category('2026赛季');
        $direct = $this->photo();
        $direct->categories()->attach($season);
        $inAlbum = $this->photo();
        $album = $this->album();
        $album->categories()->attach($season);
        $album->photos()->attach($inAlbum);

        $this->get('/search?'.http_build_query(['q' => '2026赛季']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('search.photos.data', 2)
                ->where('search.photos.meta.total', 2));
        $this->get('/albums?'.http_build_query(['q' => '2026赛季']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('albumIndex.albums.data', 1)
                ->where('albumIndex.albums.data.0.id', $album->id));
    }

    public function test_keyword_matches_tags_even_when_image_title_does_not_contain_them(): void
    {
        $photo = $this->photo();
        foreach (['捧杯', '捧杯庆祝'] as $name) {
            $tag = Tag::create(['name' => $name]);
            $photo->tags()->attach($tag);
        }
        $this->photo(['status' => 'draft'])->tags()->attach($tag);

        $this->get('/search?'.http_build_query(['q' => '捧杯']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('search.photos.data', 1)
                ->where('search.photos.data.0.uuid', $photo->uuid));
    }

    public function test_hidden_albums_categories_and_category_parents_cannot_supply_search_matches(): void
    {
        foreach (['draft', 'hidden'] as $status) {
            $album = $this->album('内部关键词', ['status' => $status]);
            $album->photos()->attach($this->photo());
        }
        foreach (['hidden-child', 'hidden-parent'] as $kind) {
            $parent = Category::create(['name' => '资料组', 'slug' => $kind, 'visibility' => $kind === 'hidden-parent' ? 'hidden' : 'public']);
            $category = Category::create(['name' => '内部关键词', 'slug' => Str::uuid()->toString(), 'parent_id' => $parent->id, 'visibility' => $kind === 'hidden-child' ? 'hidden' : 'public']);
            $this->photo()->categories()->attach($category);
            $album = $this->album();
            $album->categories()->attach($category);
            $album->photos()->attach($this->photo());
        }

        $this->get('/search?'.http_build_query(['q' => '内部关键词']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('search.photos.data', 0));
        $this->get('/albums?'.http_build_query(['q' => '内部关键词']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('albumIndex.albums.data', 0));
    }

    public function test_related_keyword_match_still_intersects_with_image_filters(): void
    {
        $album = $this->album('决赛庆祝');
        $portrait = $this->photo(['width' => 1000, 'height' => 2000]);
        $landscape = $this->photo(['width' => 2000, 'height' => 1000]);
        $album->photos()->attach([$portrait->id, $landscape->id]);
        $this->photo(['width' => 1000, 'height' => 2000]);

        $this->get('/search?'.http_build_query(['q' => '决赛', 'orientation' => 'portrait']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('search.photos.data', 1)
                ->where('search.photos.data.0.uuid', $portrait->uuid));
    }

    public function test_album_keyword_match_still_intersects_with_selected_category(): void
    {
        $season = $this->category('2026赛季');
        $matching = $this->album();
        $matching->categories()->attach($season);
        $matching->photos()->attach($this->photo());
        $this->album('2026赛季另一相册')->photos()->attach($this->photo());

        $this->get('/albums?'.http_build_query(['q' => '2026赛季', 'categories' => ['season' => $season->id]]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('albumIndex.albums.data', 1)
                ->where('albumIndex.albums.data.0.id', $matching->id));
    }

    private function photo(array $attributes = []): Photo
    {
        return Photo::create([...['title' => '现场图片', 'status' => 'published', 'published_at' => now()], ...$attributes]);
    }

    private function album(string $title = '现场相册', array $attributes = []): Album
    {
        return Album::create([...['title' => $title, 'slug' => Str::uuid()->toString(), 'status' => 'published', 'published_at' => now()], ...$attributes]);
    }

    private function category(string $name): Category
    {
        $root = Category::create(['name' => '赛季', 'slug' => 'season', 'visibility' => 'public']);

        return Category::create(['name' => $name, 'slug' => 'season-2026', 'parent_id' => $root->id, 'visibility' => 'public']);
    }
}
