<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemSettings;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\User;
use App\Services\HomepageSettings;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HomepageSettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_table_is_created_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertTrue(Schema::hasColumns('settings', [
            'group',
            'key',
            'value',
            'description',
            'updated_by',
        ]));
    }

    public function test_setting_model_stores_structured_json_value(): void
    {
        $user = User::factory()->create();

        Setting::setValue('home', 'latest_photos', [
            'enabled' => true,
            'display_count' => 15,
        ], $user, '首页最新照片模块');

        $setting = Setting::query()->where('group', 'home')->where('key', 'latest_photos')->firstOrFail();

        $this->assertSame('首页最新照片模块', $setting->description);
        $this->assertSame($user->id, $setting->updated_by);
        $this->assertSame(15, $setting->value['display_count']);
    }

    public function test_homepage_settings_returns_defaults_without_saved_rows(): void
    {
        $state = app(HomepageSettings::class)->formState();

        $this->assertSame('梅西影像档案库', $state['site']['name']);
        $this->assertSame(15, $state['latest_photos']['display_count']);
        $this->assertSame('/albums', $state['category_module']['more_url']);
    }

    public function test_homepage_settings_can_be_saved_by_grouped_keys(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $user = User::factory()->create();
        $photo = $this->publishedPhoto('2022 世界杯决赛捧杯');
        $album = Album::query()->create([
            'title' => '2022 世界杯决赛',
            'slug' => '2022-world-cup-final',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $album->photos()->sync([$photo->id]);
        $categoryId = Category::query()->children()->whereHas('parent', fn ($query) => $query->where('slug', 'competition'))->value('id');

        $state = HomepageSettings::defaults();
        $state['site']['name'] = 'Messi Archive';
        $state['latest_photos']['pinned_photo_ids'] = [$photo->id];
        $state['category_module']['tabs'] = [[
            'enabled' => true,
            'category_id' => $categoryId,
        ]];
        $state['topic_module']['items'] = [[
            'enabled' => true,
            'title' => '金球奖',
            'url' => '/topics/ballon-dor',
        ]];

        app(HomepageSettings::class)->save($state, $user);

        $this->assertSame('Messi Archive', Setting::value('site', 'basic')['name']);
        $this->assertSame([$photo->id], Setting::value('home', 'latest_photos')['pinned_photo_ids']);
        $this->assertSame($categoryId, Setting::value('home', 'category_module')['tabs'][0]['category_id']);
        $this->assertSame('金球奖', Setting::value('home', 'topic_module')['items'][0]['title']);
        $this->assertSame($user->id, Setting::query()->where('group', 'site')->where('key', 'basic')->value('updated_by'));
    }

    public function test_homepage_settings_rejects_non_public_photos(): void
    {
        $draft = Photo::query()->create([
            'title' => '草稿图片',
            'status' => 'draft',
        ]);
        $state = HomepageSettings::defaults();
        $state['latest_photos']['pinned_photo_ids'] = [$draft->id];

        $this->expectException(ValidationException::class);

        app(HomepageSettings::class)->save($state);
    }

    public function test_homepage_settings_rejects_hidden_category_navigation(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $root = Category::query()->roots()->where('slug', 'competition')->firstOrFail();
        $hiddenChild = Category::query()->create([
            'parent_id' => $root->id,
            'name' => '隐藏赛事导航',
            'slug' => 'hidden-competition-navigation',
            'visibility' => 'hidden',
            'is_system' => false,
            'required_for_publish' => false,
        ]);
        $state = HomepageSettings::defaults();
        $state['category_module']['tabs'] = [[
            'enabled' => true,
            'category_id' => $hiddenChild->id,
        ]];

        $this->expectException(ValidationException::class);

        app(HomepageSettings::class)->save($state);
    }

    public function test_admin_can_visit_system_settings_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(SystemSettings::getUrl())->assertOk();
    }

    private function publishedPhoto(string $title): Photo
    {
        $photo = Photo::query()->create([
            'title' => $title,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $categoryIds = Category::query()->children()->where('name', '待补充')->pluck('id');
        $photo->categories()->sync($categoryIds);

        return $photo;
    }
}
