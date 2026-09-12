<?php

namespace Tests\Feature;

use App\Filament\Resources\PhotoUploadBatches\PhotoUploadBatchResource;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\Tag;
use App\Models\User;
use App\Services\PhotoBatchOrganizer;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoUploadBatchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_visit_photo_upload_batch_resource_and_batch_photos_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'standalone',
            'status' => 'completed',
            'total_count' => 1,
            'success_count' => 1,
        ]);
        Photo::query()->create([
            'title' => '2022 世界杯决赛',
            'photo_upload_batch_id' => $batch->id,
        ]);

        $this->get(PhotoUploadBatchResource::getUrl())->assertOk();
        $this->get(PhotoUploadBatchResource::getUrl('photos', ['record' => $batch]))->assertOk();
    }

    public function test_batch_publish_only_publishes_photos_that_pass_confirmed_rules(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'standalone',
            'status' => 'completed',
            'total_count' => 4,
            'success_count' => 4,
        ]);
        $categoryIds = Category::query()->children()->whereIn('parent_id', Category::requiredRootIds())->where('name', '待补充')->pluck('id');
        $publishable = $this->photoInBatch($batch, '可发布图片');
        $publishable->categories()->sync($categoryIds);

        $restricted = $this->photoInBatch($batch, '受限图片', ['copyright_status' => 'restricted']);
        $restricted->categories()->sync($categoryIds);

        $incomplete = $this->photoInBatch($batch, '分类缺失图片');
        $incomplete->categories()->sync($categoryIds->take(2));

        $archived = $this->photoInBatch($batch, '归档图片', ['status' => 'archived']);
        $archived->categories()->sync($categoryIds);

        $result = app(PhotoBatchOrganizer::class)->publish($batch->photos()->get());

        $this->assertSame(1, $result['published']);
        $this->assertSame(3, $result['failed']);
        $this->assertSame('published', $publishable->refresh()->status);
        $this->assertSame('draft', $restricted->refresh()->status);
        $this->assertSame('draft', $incomplete->refresh()->status);
        $this->assertSame('archived', $archived->refresh()->status);
    }

    public function test_bulk_append_tags_does_not_remove_existing_tags(): void
    {
        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'standalone',
            'status' => 'completed',
            'total_count' => 1,
            'success_count' => 1,
        ]);
        $existingTag = Tag::query()->create(['name' => '捧杯', 'type' => '荣誉']);
        $newTag = Tag::query()->create(['name' => '微笑', 'type' => '情绪']);
        $photo = $this->photoInBatch($batch, '追加标签测试');
        $photo->tags()->sync([$existingTag->id]);

        app(PhotoBatchOrganizer::class)->appendTags(collect([$photo]), [$newTag->id]);

        $this->assertEqualsCanonicalizing(
            [$existingTag->id, $newTag->id],
            $photo->tags()->pluck('tags.id')->all(),
        );
    }

    public function test_standalone_bulk_categories_must_include_required_root_children(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'standalone',
            'status' => 'completed',
            'total_count' => 1,
            'success_count' => 1,
        ]);
        $photo = $this->photoInBatch($batch, '批量分类测试');
        $categoryIds = Category::query()->children()->whereIn('parent_id', Category::requiredRootIds())->where('name', '待补充')->pluck('id');
        $organizer = app(PhotoBatchOrganizer::class);

        $failed = $organizer->syncStandaloneCategories(collect([$photo]), $categoryIds->take(2)->all());
        $this->assertSame(['updated' => 0, 'failed' => 1], $failed);
        $this->assertSame(0, $photo->categories()->count());

        $updated = $organizer->syncStandaloneCategories(collect([$photo]), $categoryIds->all());
        $this->assertSame(['updated' => 1, 'failed' => 0], $updated);
        $this->assertTrue($photo->refresh()->hasCompleteCategorySet());
    }

    public function test_batch_common_fields_can_be_applied_to_selected_photos(): void
    {
        $sourceUrl = 'https://example.com/source';
        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'standalone',
            'status' => 'completed',
            'total_count' => 2,
            'success_count' => 2,
        ]);
        $photos = collect([
            $this->photoInBatch($batch, '图片 A'),
            $this->photoInBatch($batch, '图片 B'),
        ]);

        $result = app(PhotoBatchOrganizer::class)->updateCommonFields($photos, [
            'source_url' => $sourceUrl,
            'copyright_status' => 'credited',
            'event_date' => '2022-12-18',
        ]);

        $this->assertSame(['updated' => 2], $result);
        $this->assertSame(2, Photo::query()->where('source_url', $sourceUrl)->count());
        $this->assertSame(2, Photo::query()->where('copyright_status', 'credited')->count());
        $this->assertSame(2, Photo::query()->whereDate('event_date', '2022-12-18')->count());
    }

    public function test_album_batch_photos_keep_album_categories(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $album = Album::query()->create([
            'title' => '2022 世界杯决赛',
            'slug' => '2022-world-cup-final',
        ]);
        $categoryIds = Category::query()->children()->whereIn('parent_id', Category::requiredRootIds())->where('name', '待补充')->pluck('id');
        $album->categories()->sync($categoryIds);
        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'album',
            'album_id' => $album->id,
            'status' => 'completed',
            'total_count' => 1,
            'success_count' => 1,
        ]);
        $photo = $this->photoInBatch($batch, '相册图片');
        $photo->albums()->attach($album->id);
        $photo->categories()->sync($album->categories()->pluck('categories.id')->all());

        $this->assertEqualsCanonicalizing($categoryIds->all(), $photo->categories()->pluck('categories.id')->all());
        $this->assertTrue($photo->hasCompleteCategorySet());
    }

    private function photoInBatch(PhotoUploadBatch $batch, string $title, array $attributes = []): Photo
    {
        return Photo::query()->create(array_merge([
            'title' => $title,
            'photo_upload_batch_id' => $batch->id,
            'display_key' => 'photos/derived/test/display.webp',
            'thumbnail_key' => 'photos/derived/test/thumbnail.webp',
        ], $attributes));
    }
}
