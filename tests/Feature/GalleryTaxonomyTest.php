<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Tags\TagResource;
use App\Models\Category;
use App\Models\Tag;
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

    public function test_tags_use_the_confirmed_phase_one_types(): void
    {
        $this->assertSame([
            '动作',
            '情绪',
            '画质',
            '人物关系',
            '荣誉',
            '画面内容',
            '服装/装备',
            '地点',
        ], array_values(Tag::TYPES));
    }

    public function test_admin_can_visit_category_and_tag_resources(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(CategoryResource::getUrl())->assertOk();
        $this->get(TagResource::getUrl())->assertOk();
    }
}
