<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemSettings;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\User;
use App\Services\HomepageSettings;
use App\Services\PublicHomepage;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
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
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->get(SystemSettings::getUrl())->assertOk();
    }

    public function test_admin_can_save_and_reload_independent_hero_focus_without_changing_images(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        Setting::setValue('home', 'hero_slides', [[
            'enabled' => true,
            'title' => '焦点测试',
            'desktop_image_path' => 'settings/hero/desktop.webp',
            'mobile_image_path' => 'settings/hero/mobile.webp',
        ]]);

        $page = Livewire::test(SystemSettings::class)->assertSee('桌面端焦点')->assertSee('手机端焦点');
        $slides = array_values($page->get('data.hero_slides'));
        $this->assertEquals(['x' => 50, 'y' => 50], $slides[0]['desktop_focus']);
        $key = array_key_first($page->get('data.hero_slides'));
        $page->set("data.hero_slides.$key.desktop_focus", ['x' => 25, 'y' => 20])
            ->set("data.hero_slides.$key.mobile_focus", ['x' => 75, 'y' => 10])
            ->call('save')->assertHasNoFormErrors();

        $saved = app(HomepageSettings::class)->formState()['hero_slides'][0];
        $this->assertEquals(['x' => 25, 'y' => 20], $saved['desktop_focus']);
        $this->assertEquals(['x' => 75, 'y' => 10], $saved['mobile_focus']);
        $this->assertSame('settings/hero/desktop.webp', $saved['desktop_image_path']);
        $this->assertSame('settings/hero/mobile.webp', $saved['mobile_image_path']);
        $reloaded = Livewire::test(SystemSettings::class);
        $this->assertEquals($saved['mobile_focus'], array_values($reloaded->get('data.hero_slides'))[0]['mobile_focus']);
    }

    public function test_new_upload_can_be_previewed_and_saved_with_focus(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        Setting::setValue('home', 'hero_slides', [['title' => '新上传', 'enabled' => true]]);
        $page = Livewire::test(SystemSettings::class);
        $key = array_key_first($page->get('data.hero_slides'));
        $page->set("data.hero_slides.$key.desktop_image_path", [UploadedFile::fake()->image('focus.jpg', 1200, 800)])
            ->set("data.hero_slides.$key.desktop_focus", ['x' => 35, 'y' => 20])
            ->assertSee('桌面端焦点裁切预览')
            ->call('save')->assertHasNoFormErrors();
        $slide = Setting::value('home', 'hero_slides')[0];
        Storage::disk('public')->assertExists($slide['desktop_image_path']);
        $this->assertEquals(['x' => 35, 'y' => 20], $slide['desktop_focus']);
    }

    public function test_invalid_hero_focus_is_rejected_without_saving_settings(): void
    {
        foreach ([['x' => -1, 'y' => 50], ['x' => 50, 'y' => 101], ['x' => 'invalid', 'y' => 20], ['x' => 20]] as $focus) {
            $state = HomepageSettings::defaults();
            $state['hero_slides'] = [['desktop_focus' => $focus]];
            try {
                app(HomepageSettings::class)->save($state);
                $this->fail('非法焦点不应被保存');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
                $this->assertNull(Setting::value('home', 'hero_slides'));
            }
        }
    }

    public function test_site_basic_copyright_and_registration_save_to_public_footer(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        Livewire::test(SystemSettings::class)
            ->set('data.site.copyright_text', '更新后的站点署名')
            ->set('data.site.icp_text', '测试备案号')
            ->call('save')->assertHasNoFormErrors();
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('home.footer.copyright_text', '更新后的站点署名')
            ->where('home.footer.icp_text', '测试备案号'));
        $this->assertSame('更新后的站点署名', app(PublicHomepage::class)->shell()['footer']['copyright_text']);
        Livewire::test(SystemSettings::class)
            ->set('data.site.icp_text', null)
            ->call('save')->assertHasNoFormErrors();
        $this->assertNull(app(PublicHomepage::class)->shell()['footer']['icp_text']);
    }

    public function test_legacy_footer_text_is_preserved_when_site_basic_has_no_fields(): void
    {
        Setting::setValue('site', 'basic', ['name' => '旧站点']);
        Setting::setValue('site', 'footer', ['copyright_text' => '原有署名', 'icp_text' => '原有备案']);
        $state = app(HomepageSettings::class)->formState();
        $this->assertSame('原有署名', $state['site']['copyright_text']);
        $this->assertSame('原有备案', $state['site']['icp_text']);
        Setting::setValue('site', 'basic', ['copyright_text' => '站点基础新署名', 'icp_text' => '新备案']);
        $footer = app(PublicHomepage::class)->shell()['footer'];
        $this->assertSame('站点基础新署名', $footer['copyright_text']);
        $this->assertSame('新备案', $footer['icp_text']);
    }

    public function test_registration_link_can_be_saved_and_cleared(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        Livewire::test(SystemSettings::class)
            ->set('data.site.icp_text', '备案号')
            ->set('data.site.icp_url', 'https://example.com/registration')
            ->call('save')->assertHasNoFormErrors();
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('home.footer.icp_url', 'https://example.com/registration'));
        $state = app(HomepageSettings::class)->formState();
        $this->assertSame('https://example.com/registration', $state['site']['icp_url']);
        $state['site']['icp_url'] = '';
        app(HomepageSettings::class)->save($state);
        $this->assertNull(app(PublicHomepage::class)->shell()['footer']['icp_url']);
    }

    public function test_registration_link_rejects_unsafe_or_invalid_urls(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,test', '/relative', 'invalid'] as $url) {
            $state = HomepageSettings::defaults();
            $state['site']['icp_url'] = $url;
            try {
                app(HomepageSettings::class)->save($state);
                $this->fail('非法备案链接不应保存');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('data.site.icp_url', $exception->errors());
            }
        }
        Setting::setValue('site', 'basic', ['icp_url' => 'javascript:alert(1)']);
        $this->assertNull(app(PublicHomepage::class)->shell()['footer']['icp_url']);
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
