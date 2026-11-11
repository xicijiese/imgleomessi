<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Tags\TagResource;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\User;
use Database\Seeders\GalleryTaxonomySeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_a_system_child_with_photo_and_album_links_without_deleting_media(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $child = Category::where('slug', 'pending-career-stage')->firstOrFail();
        $photo = Photo::create(['title' => '保留图片']);
        $album = Album::create(['title' => '保留相册', 'slug' => 'keep-album']);
        $child->photos()->attach($photo);
        $child->albums()->attach($album);
        Livewire::test(ManageCategories::class)
            ->callAction(TestAction::make('delete')->table($child));
        $this->assertDatabaseMissing('categories', ['id' => $child->id]);
        $this->assertDatabaseHas('photos', ['id' => $photo->id]);
        $this->assertDatabaseHas('albums', ['id' => $album->id]);
        $this->assertDatabaseMissing('photo_category', ['category_id' => $child->id]);
        $this->assertDatabaseMissing('album_category', ['category_id' => $child->id]);
        $this->assertNotNull(Category::where('slug', 'career-stage')->firstOrFail()->deletionBlockReason());
    }

    public function test_gallery_taxonomy_seeder_creates_fixed_root_categories_with_pending_children(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $firstCount = Category::query()->count();
        $this->seed(GalleryTaxonomySeeder::class);

        $this->assertSame(8, Category::query()->roots()->count());
        $this->assertSame($firstCount, Category::query()->count());

        foreach (GalleryTaxonomySeeder::CHILDREN as $rootSlug => $children) {
            $root = Category::query()->where('slug', $rootSlug)->firstOrFail();

            foreach ($children as $child) {
                $this->assertDatabaseHas('categories', [
                    'parent_id' => $root->id,
                    'slug' => $child['slug'],
                    'name' => $child['name'],
                    'visibility' => 'public',
                ]);
            }
        }

        $this->assertSame(Category::REQUIRED_ROOT_SLUGS, Category::query()->requiredForPublish()->pluck('slug')->all());

        foreach (Category::ROOT_CATEGORIES as $slug => $name) {
            $root = Category::query()->where('slug', $slug)->firstOrFail();

            $this->assertTrue($root->isRoot());
            $this->assertTrue($root->is_system);
            $this->assertSame($name, $root->name);

            $this->assertDatabaseHas('categories', [
                'parent_id' => $root->id,
                'slug' => 'pending-'.$slug,
                'name' => '待补充',
                'is_system' => true,
            ]);
        }
    }

    public function test_unreferenced_custom_child_category_can_be_deleted(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $root = Category::query()->where('slug', 'career-stage')->firstOrFail();
        $category = Category::query()->create([
            'parent_id' => $root->id,
            'name' => '临时分类',
            'slug' => 'temporary-category',
            'visibility' => 'public',
            'is_system' => false,
        ]);

        $this->assertNull($category->deletionBlockReason());

        $category->delete();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_child_with_photo_reference_is_deletable(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $root = Category::query()->where('slug', 'career-stage')->firstOrFail();
        $category = Category::query()->create([
            'parent_id' => $root->id,
            'name' => '已使用分类',
            'slug' => 'used-by-photo',
            'visibility' => 'public',
            'is_system' => false,
        ]);
        $photo = Photo::query()->create(['title' => '关联图片']);
        $category->photos()->attach($photo);

        $this->assertNull($category->deletionBlockReason());
    }

    public function test_child_with_album_reference_is_deletable(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $root = Category::query()->where('slug', 'career-stage')->firstOrFail();
        $category = Category::query()->create([
            'parent_id' => $root->id,
            'name' => '已使用相册分类',
            'slug' => 'used-by-album',
            'visibility' => 'public',
            'is_system' => false,
        ]);
        $album = Album::query()->create([
            'title' => '关联相册',
            'slug' => 'used-by-category-album',
        ]);
        $category->albums()->attach($album);

        $this->assertNull($category->deletionBlockReason());
    }

    public function test_system_child_is_deletable(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $category = Category::query()->where('slug', 'pending-career-stage')->firstOrFail();

        $this->assertNull($category->deletionBlockReason());
    }

    public function test_admin_can_visit_category_and_tag_resources(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->get(CategoryResource::getUrl())->assertOk();
        $this->get(TagResource::getUrl())->assertOk();
    }
}
