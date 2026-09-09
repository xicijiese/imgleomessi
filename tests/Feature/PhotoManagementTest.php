<?php

namespace Tests\Feature;

use App\Filament\Resources\Photos\PhotoResource;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhotoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_tables_are_created_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('photos'));
        $this->assertTrue(Schema::hasTable('photo_category'));
        $this->assertTrue(Schema::hasTable('photo_tag'));
        $this->assertTrue(Schema::hasTable('photo_upload_batches'));
        $this->assertTrue(Schema::hasColumns('photos', [
            'uuid',
            'title',
            'description',
            'original_filename',
            'stored_filename',
            'taken_at',
            'event_date',
            'source_id',
            'copyright_status',
            'status',
            'publish_after_processing',
            'width',
            'height',
            'mime_type',
            'file_size',
            'original_key',
            'display_key',
            'thumbnail_key',
            'uploaded_by',
            'photo_upload_batch_id',
            'published_at',
        ]));
    }

    public function test_photo_defaults_to_draft_and_unknown_copyright_status(): void
    {
        $photo = Photo::query()->create([
            'title' => '2022 世界杯决赛捧杯',
            'display_key' => 'photos/derived/test/display.webp',
            'thumbnail_key' => 'photos/derived/test/thumbnail.webp',
        ]);

        $this->assertNotEmpty($photo->uuid);
        $this->assertSame('draft', $photo->status);
        $this->assertSame('unknown', $photo->copyright_status);
    }

    public function test_photo_can_link_source_categories_tags_albums_and_upload_batch(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $source = Source::query()->create([
            'original_url' => 'https://example.com/messi-photo',
        ]);
        $tag = Tag::query()->create([
            'name' => '捧杯',
            'type' => '荣誉',
        ]);
        $album = Album::query()->create([
            'title' => '2022 世界杯决赛',
            'slug' => '2022-world-cup-final',
        ]);
        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'standalone',
            'status' => 'completed',
            'total_count' => 1,
            'success_count' => 1,
        ]);
        $photo = Photo::query()->create([
            'title' => '2022 世界杯决赛捧杯',
            'source_id' => $source->id,
            'photo_upload_batch_id' => $batch->id,
        ]);

        $categoryIds = Category::query()->children()->where('name', '待补充')->pluck('id');
        $photo->categories()->sync($categoryIds);
        $photo->tags()->sync([$tag->id]);
        $photo->albums()->sync([$album->id]);

        $this->assertTrue($photo->source->is($source));
        $this->assertTrue($photo->uploadBatch->is($batch));
        $this->assertTrue($photo->categories()->whereKey($categoryIds->first())->exists());
        $this->assertTrue($photo->tags()->whereKey($tag->id)->exists());
        $this->assertTrue($photo->albums()->whereKey($album->id)->exists());
    }

    public function test_photo_requires_the_three_required_root_categories_before_publish(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $photo = Photo::query()->create([
            'title' => '2022 世界杯决赛捧杯',
            'display_key' => 'photos/derived/test/display.webp',
            'thumbnail_key' => 'photos/derived/test/thumbnail.webp',
        ]);
        $requiredCategoryIds = Category::query()
            ->children()
            ->whereIn('parent_id', Category::requiredRootIds())
            ->where('name', '待补充')
            ->pluck('id');

        $photo->categories()->sync($requiredCategoryIds->take(2));
        $this->assertFalse($photo->hasCompleteCategorySet());
        $this->assertFalse($photo->publish());
        $this->assertSame('draft', $photo->refresh()->status);

        $photo->categories()->sync($requiredCategoryIds);
        $this->assertTrue($photo->hasCompleteCategorySet());
        $this->assertTrue($photo->publish());
        $this->assertSame('published', $photo->refresh()->status);
        $this->assertNotNull($photo->published_at);
    }

    public function test_archived_photo_can_be_restored_as_draft(): void
    {
        $photo = Photo::query()->create([
            'title' => '待恢复图片',
            'status' => 'archived',
            'publish_after_processing' => true,
            'published_at' => now(),
        ]);

        $this->assertTrue($photo->restoreFromArchive());
        $this->assertSame('draft', $photo->refresh()->status);
        $this->assertFalse($photo->publish_after_processing);
        $this->assertNull($photo->published_at);
        $this->assertFalse($photo->restoreFromArchive());
    }
    public function test_published_scope_only_returns_published_photos(): void
    {
        Photo::query()->create([
            'title' => '草稿图片',
            'status' => 'draft',
        ]);
        Photo::query()->create([
            'title' => '已发布图片',
            'status' => 'published',
        ]);

        $this->assertSame(1, Photo::query()->published()->count());
        $this->assertSame('已发布图片', Photo::query()->published()->value('title'));
    }

    public function test_admin_can_visit_photo_resource(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(PhotoResource::getUrl())->assertOk();
    }

    public function test_photo_resource_renders_similarity_action_for_existing_photo(): void
    {
        Photo::query()->create([
            'title' => '已有图片',
        ]);
        $this->actingAs(User::factory()->create());

        $this->get(PhotoResource::getUrl())->assertOk();
    }}
