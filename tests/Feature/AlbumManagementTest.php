<?php

namespace Tests\Feature;

use App\Filament\Resources\Albums\AlbumResource;
use App\Filament\Resources\Albums\Pages\ManageAlbums;
use App\Models\Album;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\GalleryTaxonomySeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AlbumManagementTest extends TestCase
{
    use RefreshDatabase;

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
