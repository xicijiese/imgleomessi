<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Tags\TagResource;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\User;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryTaxonomyTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_category_with_photo_reference_cannot_be_deleted(): void
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

        $this->assertSame('该分类已关联 1 张图片，请先移除关联或将其设置为隐藏。', $category->deletionBlockReason());
    }

    public function test_category_with_album_reference_cannot_be_deleted(): void
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

        $this->assertSame('该分类已关联 1 个相册，请先移除关联或将其设置为隐藏。', $category->deletionBlockReason());
    }

    public function test_system_category_cannot_be_deleted(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $category = Category::query()->where('slug', 'pending-career-stage')->firstOrFail();

        $this->assertSame('系统分类不可删除。', $category->deletionBlockReason());
    }
    public function test_admin_can_visit_category_and_tag_resources(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->get(CategoryResource::getUrl())->assertOk();
        $this->get(TagResource::getUrl())->assertOk();
    }
}
