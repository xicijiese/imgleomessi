<?php

namespace Tests\Feature;

use App\Filament\Resources\Albums\AlbumResource;
use App\Models\Album;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AlbumManagementTest extends TestCase
{
    use RefreshDatabase;

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
        $this->actingAs(User::factory()->create());

        $this->get(AlbumResource::getUrl())->assertOk();
    }
}
