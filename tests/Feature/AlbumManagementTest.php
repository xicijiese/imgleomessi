<?php

namespace Tests\Feature;

use App\Filament\Resources\Albums\AlbumResource;
use App\Filament\Resources\Albums\Pages\ManageAlbums;
use App\Filament\Resources\Photos\Pages\ManagePhotos;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\User;
use App\Services\PhotoBatchOrganizer;
use Database\Seeders\GalleryTaxonomySeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AlbumManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_album_reorder_persists_across_pages_and_new_album_goes_first(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $albums = collect(range(1, 25))->map(fn ($i) => Album::create(['title' => '相册'.$i, 'slug' => 'sort-'.$i, 'sort_order' => 0, 'created_at' => now()->subDays(26 - $i)]));
        $page = Livewire::test(ManageAlbums::class);
        $page->assertCanSeeTableRecords($albums->reverse()->take(20), inOrder: true);
        $page->call('toggleTableReordering')->assertSet('isTableReordering', true)
            ->assertCanSeeTableRecords($albums->reverse(), inOrder: true);
        $order = $albums->pluck('id')->all();
        $page->call('reorderTable', $order)->call('toggleTableReordering');
        Livewire::test(ManageAlbums::class)->assertCanSeeTableRecords($albums->take(20), inOrder: true);
        Livewire::test(ManageAlbums::class)->callAction('create', ['title' => '最新相册', 'slug' => 'new-first'])->assertHasNoActionErrors();
        $latest = Album::where('slug', 'new-first')->sole();
        $this->assertLessThan(1, $latest->sort_order);
        Livewire::test(ManageAlbums::class)->assertCanSeeTableRecords(collect([$latest])->merge($albums->take(19)), inOrder: true);
    }

    public function test_album_and_upload_time_options_are_sorted_newest_first(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        foreach (['season' => ['04/05赛季', '22/23赛季', '2026赛季', '2025赛季', '待补充'], 'year' => ['2003年之前', '2028年', '2028年之后', '2026年', '待补充']] as $slug => $names) {
            $root = Category::create(['name' => $slug, 'slug' => $slug]);
            foreach ($names as $index => $name) {
                Category::create(['name' => $name, 'slug' => $slug.'-'.$index, 'parent_id' => $root->id]);
            }
            $expected = $slug === 'season' ? ['2026赛季', '2025赛季', '22/23赛季', '04/05赛季', '待补充'] : ['2028年之后', '2028年', '2026年', '2003年之前', '待补充'];
            foreach ([ManageAlbums::class => 'create', ManagePhotos::class => 'bulkUpload'] as $page => $action) {
                Livewire::test($page)->mountAction($action)->assertFormFieldExists('category_root_'.$root->id,
                    fn (Select $field): bool => array_values($field->getOptions()) === $expected);
            }
        }
    }

    public function test_album_saves_all_eight_multiple_categories_and_preserves_them_when_editing_title(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $data = ['title' => '多分类相册', 'slug' => 'multi-category-album'];
        $expected = [];
        foreach (Category::roots()->get() as $root) {
            $ids = $root->children()->limit(1)->pluck('id')->all();
            $ids[] = Category::create(['parent_id' => $root->id, 'name' => '新增选项', 'slug' => 'multi-'.$root->slug])->id;
            $data['category_root_'.$root->id] = $ids;
            $expected = array_merge($expected, $ids);
        }
        Livewire::test(ManageAlbums::class)->callAction('create', $data)->assertHasNoActionErrors();
        $album = Album::sole();
        $this->assertEqualsCanonicalizing($expected, $album->categories()->pluck('categories.id')->all());
        $this->assertTrue($album->hasCompleteCategorySet());
        Livewire::test(ManageAlbums::class)->callAction(TestAction::make('edit')->table($album), ['title' => '修改名称'])->assertHasNoActionErrors();
        $this->assertEqualsCanonicalizing($expected, $album->categories()->pluck('categories.id')->all());
        $photo = Photo::create(['title' => '继承相册分类']);
        $photo->categories()->sync($album->categories()->pluck('categories.id'));
        $this->assertTrue($photo->hasCompleteCategorySet());
        $this->assertTrue(app(PhotoBatchOrganizer::class)->isCompleteCategorySet($expected));
    }

    public function test_album_can_create_and_select_custom_scene_without_duplicate_categories(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        foreach (['scene', 'image-type', 'person-relation'] as $slug) {
            $root = Category::where('slug', $slug)->sole();
            $field = 'category_root_'.$root->id;
            $component = Livewire::test(ManageAlbums::class)->mountAction('create');
            $action = TestAction::make('createOption')->schemaComponent($field, 'mountedActionSchema0');
            $component->callAction($action, ['name' => '自定义测试项'])->assertHasNoActionErrors();
            $option = $root->children()->where('name', '自定义测试项')->sole();
            $component->assertSet('mountedActions.0.data.'.$field, [$option->id]);
            $component->callAction($action, ['name' => '自定义测试项'])->assertHasNoActionErrors();
            $this->assertSame(1, $root->children()->where('name', '自定义测试项')->count());
        }
    }

    public function test_create_defaults_to_published_and_generates_unique_pinyin_slug_without_changing_existing_links(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $component = Livewire::test(ManageAlbums::class)->mountAction('create');
        $component->assertSet('mountedActions.0.data.status', 'published');
        $component->set('mountedActions.0.data.title', '梅西 世界杯 2022')->callMountedAction()->assertHasNoActionErrors();
        $album = Album::sole();
        $this->assertSame('published', $album->status);
        $this->assertSame('mei-xi-shi-jie-bei-2022', $album->slug);
        Livewire::test(ManageAlbums::class)->callAction('create', ['title' => '梅西 世界杯 2022'])->assertHasNoActionErrors();
        $this->assertDatabaseHas('albums', ['slug' => 'mei-xi-shi-jie-bei-2022-2']);
        Livewire::test(ManageAlbums::class)->callAction(TestAction::make('edit')->table($album), ['title' => '改名后的相册'])->assertHasNoActionErrors();
        $this->assertSame('mei-xi-shi-jie-bei-2022', $album->fresh()->slug);
    }

    public function test_admin_can_create_and_edit_album_details_and_categories(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $root = Category::create(['name' => '生涯阶段', 'slug' => 'career-stage', 'required_for_publish' => true]);
        $first = Category::create(['name' => '阶段一', 'slug' => 'first-stage', 'parent_id' => $root->id]);
        $second = Category::create(['name' => '阶段二', 'slug' => 'second-stage', 'parent_id' => $root->id]);

        Livewire::test(ManageAlbums::class)
            ->callAction('create', [
                'title' => '相册资料测试',
                'slug' => 'album-details-test',
                'description' => '相册说明',
                'status' => 'draft',
                'sort_order' => 3,
                'category_root_'.$root->id => $first->id,
            ])
            ->assertHasNoActionErrors();

        $album = Album::query()->sole();
        $this->assertSame('相册说明', $album->description);
        $this->assertEquals([$first->id], $album->categories()->pluck('categories.id')->all());

        Livewire::test(ManageAlbums::class)
            ->callAction(TestAction::make('edit')->table($album), [
                'title' => '更新后的相册资料',
                'sort_order' => 9,
                'category_root_'.$root->id => $second->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('更新后的相册资料', $album->fresh()->title);
        $this->assertSame('draft', $album->fresh()->status);
        $this->assertSame(9, $album->fresh()->sort_order);
        $this->assertEquals([$second->id], $album->categories()->pluck('categories.id')->all());
    }

    public function test_album_tables_are_created_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('albums'));
        $this->assertTrue(Schema::hasTable('album_category'));
        $this->assertTrue(Schema::hasTable('album_photo'));
        $this->assertTrue(Schema::hasColumns('albums', [
            'title',
            'slug',
            'description',
            'cover_photo_id',
            'sort_order',
            'status',
            'published_at',
            'is_featured',
            'featured_at',
        ]));
    }

    public function test_album_defaults_to_draft_status(): void
    {
        $album = Album::query()->create([
            'title' => '2022 世界杯决赛',
            'slug' => '2022-world-cup-final',
        ]);

        $this->assertSame('draft', $album->status);
    }

    public function test_album_requires_one_child_category_from_each_required_root_category(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $album = Album::query()->create([
            'title' => '2022 世界杯决赛',
            'slug' => '2022-world-cup-final',
        ]);

        $pendingChildren = Category::query()
            ->children()
            ->whereIn('parent_id', Category::requiredRootIds())
            ->where('name', '待补充')
            ->pluck('id');

        $album->categories()->sync($pendingChildren);
        $this->assertTrue($album->hasCompleteCategorySet());

        $album->categories()->sync($pendingChildren->take(2));
        $this->assertFalse($album->hasCompleteCategorySet());
    }

    public function test_album_photo_allows_the_same_photo_id_in_multiple_albums(): void
    {
        $albumA = Album::query()->create([
            'title' => '相册 A',
            'slug' => 'album-a',
        ]);
        $albumB = Album::query()->create([
            'title' => '相册 B',
            'slug' => 'album-b',
        ]);

        DB::table('album_photo')->insert([
            ['album_id' => $albumA->id, 'photo_id' => 1001],
            ['album_id' => $albumB->id, 'photo_id' => 1001],
        ]);

        $this->assertSame(2, DB::table('album_photo')->where('photo_id', 1001)->count());
    }

    public function test_admin_can_visit_album_resource(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->get(AlbumResource::getUrl())->assertOk();
    }
}
