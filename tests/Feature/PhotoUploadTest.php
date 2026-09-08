<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\Source;
use App\Models\User;
use App\Services\PhotoUploadService;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.default' => 'public']);
        Storage::fake('public');
    }

    public function test_photo_upload_batch_table_is_created_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('photo_upload_batches'));
        $this->assertTrue(Schema::hasColumns('photo_upload_batches', [
            'mode',
            'album_id',
            'uploaded_by',
            'status',
            'total_count',
            'success_count',
            'failed_count',
            'note',
        ]));
    }

    public function test_uploaded_photo_is_stored_with_system_filename_and_draft_record(): void
    {
        $uploader = User::factory()->create();
        $source = Source::query()->create([
            'original_url' => 'https://example.com/original-photo',
        ]);
        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'standalone',
            'uploaded_by' => $uploader->id,
            'status' => 'processing',
            'total_count' => 1,
        ]);
        $file = UploadedFile::fake()->image('messi-final.JPG', 1200, 800)->size(512);

        $photo = app(PhotoUploadService::class)->store($file, uploader: $uploader, batch: $batch, attributes: [
            'source_id' => $source->id,
            'copyright_status' => 'credited',
        ]);

        $this->assertSame('messi-final.JPG', $photo->original_filename);
        $this->assertMatchesRegularExpression('/^\d{8}-\d{6}-[A-Z0-9]{6}\.jpg$/', $photo->stored_filename);
        $this->assertSame($photo->stored_filename, $photo->title);
        $this->assertSame('draft', $photo->status);
        $this->assertFalse($photo->publish_after_processing);
        $this->assertSame('credited', $photo->copyright_status);
        $this->assertSame($source->id, $photo->source_id);
        $this->assertSame($uploader->id, $photo->uploaded_by);
        $this->assertSame($batch->id, $photo->photo_upload_batch_id);
        $this->assertTrue($photo->uploadBatch->is($batch));
        $this->assertSame(1200, $photo->width);
        $this->assertSame(800, $photo->height);
        $this->assertNotNull($photo->uuid);
        Storage::disk('public')->assertExists($photo->original_key);
    }

    public function test_uploading_same_original_filename_creates_separate_photo_records(): void
    {
        $service = app(PhotoUploadService::class);

        $first = $service->store(UploadedFile::fake()->image('same.jpg'));
        $second = $service->store(UploadedFile::fake()->image('same.jpg'));

        $this->assertSame(2, Photo::query()->count());
        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->stored_filename, $second->stored_filename);
    }

    public function test_album_upload_attaches_photo_and_inherits_album_categories(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $album = Album::query()->create([
            'title' => '2022 世界杯决赛',
            'slug' => '2022-world-cup-final',
        ]);
        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'album',
            'album_id' => $album->id,
            'status' => 'processing',
            'total_count' => 1,
        ]);
        $categoryIds = Category::query()->children()->where('name', '待补充')->pluck('id');
        $album->categories()->sync($categoryIds);

        $photo = app(PhotoUploadService::class)->store(
            UploadedFile::fake()->image('album-photo.jpg'),
            album: $album,
            batch: $batch,
        );

        $this->assertSame($batch->id, $photo->photo_upload_batch_id);
        $this->assertTrue($batch->photos()->whereKey($photo->id)->exists());
        $this->assertTrue($photo->albums()->whereKey($album->id)->exists());
        $this->assertEqualsCanonicalizing($categoryIds->all(), $photo->categories()->pluck('categories.id')->all());
        $this->assertTrue($photo->hasCompleteCategorySet());
    }
}
