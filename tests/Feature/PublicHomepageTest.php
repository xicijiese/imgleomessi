<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Photo;
use App\Models\Setting;
use App\Services\HomepageSettings;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_homepage_with_default_payload(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('home.site.name', '梅西影像档案库')
                ->where('home.latest_photos.display_count', 15)
                ->where('home.hero_slides.0.title', '梅西影像档案库')
            );
    }

    public function test_homepage_latest_photos_only_exposes_public_photos_with_pinned_first(): void
    {
        $pinned = Photo::query()->create([
            'title' => '指定置顶图片',
            'status' => 'published',
            'published_at' => now()->subDays(10),
            'display_key' => 'photos/display/pinned.webp',
            'thumbnail_key' => 'photos/thumb/pinned.webp',
        ]);
        $recent = Photo::query()->create([
            'title' => '最新发布图片',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'thumbnail_key' => 'photos/thumb/recent.webp',
        ]);
        $draft = Photo::query()->create([
            'title' => '草稿图片',
            'status' => 'draft',
            'published_at' => now(),
        ]);
        $restricted = Photo::query()->create([
            'title' => '受限图片',
            'status' => 'published',
            'copyright_status' => 'restricted',
            'published_at' => now(),
        ]);

        Setting::setValue('home', 'latest_photos', [
            'enabled' => true,
            'title' => '最新照片',
            'display_count' => 3,
            'sort' => 'published_at_desc',
            'pinned_photo_ids' => [$pinned->id, $draft->id, $restricted->id],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('home.latest_photos.items', 2)
                ->where('home.latest_photos.items.0.title', '指定置顶图片')
                ->where('home.latest_photos.items.0.image_url', '/storage/photos/thumb/pinned.webp')
                ->where('home.latest_photos.items.1.title', '最新发布图片')
                ->where('home.latest_photos.items.1.image_url', '/storage/photos/thumb/recent.webp')
            );
    }

    public function test_homepage_category_module_auto_fills_public_photos_for_configured_root_category(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $competition = Category::query()->roots()->where('slug', 'competition')->firstOrFail();
        $worldCup = Category::query()->children()->where('parent_id', $competition->id)->where('name', '待补充')->firstOrFail();
        $photo = Photo::query()->create([
            'title' => '世界杯公开图片',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $photo->categories()->sync([$worldCup->id]);

        $state = HomepageSettings::defaults();
        $state['category_module']['tabs'] = [[
            'enabled' => true,
            'category_id' => $competition->id,
            'label' => '赛事',
            'photo_ids' => [],
            'album_ids' => [],
        ]];
        Setting::setValue('home', 'category_module', $state['category_module']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('home.category_module.tabs.0.label', '赛事')
                ->where('home.category_module.tabs.0.items.0.title', '世界杯公开图片')
            );
    }
}
