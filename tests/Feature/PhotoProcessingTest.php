<?php

namespace Tests\Feature;

use App\Filament\Resources\PhotoSimilarityCandidates\PhotoSimilarityCandidateResource;
use App\Filament\Resources\ProcessingJobs\ProcessingJobResource;
use App\Jobs\ProcessPhotoAnalysisJob;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoSimilarityCandidate;
use App\Models\ProcessingJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\PhotoProcessingService;
use App\Services\PhotoSimilarityService;
use App\Services\PhotoUploadService;
use App\Services\StorageSettings;
use App\Services\TencentDataWanxiangService;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Qcloud\Cos\Client;
use Tests\TestCase;

class PhotoProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.default' => 'public']);
        Storage::fake('public');
        Queue::fake();
    }

    public function test_processing_tables_are_created_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('processing_jobs'));
        $this->assertTrue(Schema::hasTable('photo_analysis_results'));
        $this->assertTrue(Schema::hasTable('photo_similarity_candidates'));
        $this->assertTrue(Schema::hasColumns('processing_jobs', [
            'type',
            'photo_id',
            'status',
            'attempts',
            'payload',
            'error_message',
            'processed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('photo_similarity_candidates', [
            'photo_id',
            'candidate_photo_id',
            'distance',
            'similarity_score',
            'status',
            'reviewed_by',
            'reviewed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('photo_analysis_results', [
            'photo_id',
            'width',
            'height',
            'mime_type',
            'file_size',
            'sha256_hash',
            'perceptual_hash',
            'exif_json',
            'ocr_text',
            'ci_labels_json',
            'ci_quality_json',
            'error_message',
            'processed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('photo_similarity_candidates', [
            'photo_id',
            'candidate_photo_id',
            'distance',
            'similarity_score',
            'status',
            'reviewed_by',
            'reviewed_at',
        ]));
    }

    public function test_uploaded_photo_creates_pending_metadata_and_hash_jobs(): void
    {
        $photo = app(PhotoUploadService::class)->store(
            UploadedFile::fake()->image('messi-upload.jpg', 900, 600),
        );

        $this->assertSame(3, $photo->processingJobs()->count());
        $this->assertTrue($photo->processingJobs()->where('type', 'metadata')->where('status', 'pending')->exists());
        $this->assertTrue($photo->processingJobs()->where('type', 'hash')->where('status', 'pending')->exists());
        Queue::assertPushed(ProcessPhotoAnalysisJob::class, 3);
    }

    public function test_cos_datawanxiang_switch_adds_a_derivative_processing_job(): void
    {
        Setting::setValue('storage', 'config', [
            'mode' => 'cos',
            'cos' => [
                'secret_id' => 'test-secret-id',
                'secret_key' => encrypt('test-secret-key'),
                'region' => 'ap-guangzhou',
                'bucket' => 'testbucket-1250000000',
                'cdn_url' => '',
                'datawanxiang_enabled' => true,
            ],
        ]);

        $photo = Photo::query()->create([
            'title' => '数据万象入队测试',
            'original_key' => 'photos/originals/test.jpg',
        ]);

        app(PhotoProcessingService::class)->createDefaultJobs($photo);

        $this->assertTrue($photo->processingJobs()->where('type', 'datawanxiang_derivatives')->where('status', 'pending')->exists());
        Queue::assertPushed(ProcessPhotoAnalysisJob::class, 3);
    }

    public function test_default_processing_does_not_create_smart_label_job(): void
    {
        $photo = Photo::query()->create([
            'title' => '停用智能标签测试',
            'original_key' => 'photos/originals/no-labels.jpg',
        ]);
        $wanxiang = Mockery::mock(TencentDataWanxiangService::class);
        $wanxiang->shouldReceive('enabled')->once()->andReturn(true);
        $this->app->instance(TencentDataWanxiangService::class, $wanxiang);

        $jobs = app(PhotoProcessingService::class)->createDefaultJobs($photo, true);

        $this->assertSame(['metadata', 'hash', 'datawanxiang_derivatives', 'ocr'], collect($jobs)->map->type->all());
        $this->assertFalse($photo->processingJobs()->where('type', 'labels')->exists());
    }

    public function test_local_processing_generates_display_and_thumbnail_webp_files(): void
    {
        $image = UploadedFile::fake()->image('local-derivatives.jpg', 1200, 800);
        $originalKey = 'photos/originals/local-derivatives.jpg';
        Storage::disk('public')->put($originalKey, file_get_contents($image->getRealPath()));

        $photo = Photo::query()->create([
            'title' => '本地派生图测试',
            'original_key' => $originalKey,
        ]);

        $service = app(PhotoProcessingService::class);
        $processed = $service->process($service->createJob($photo, 'datawanxiang_derivatives'));

        $this->assertSame('done', $processed->status);
        $photo->refresh();
        $this->assertSame('photos/derived/'.$photo->uuid.'/display.webp', $photo->display_key);
        $this->assertSame('photos/derived/'.$photo->uuid.'/thumbnail.webp', $photo->thumbnail_key);
        Storage::disk('public')->assertExists($photo->display_key);
        Storage::disk('public')->assertExists($photo->thumbnail_key);

        $displayInfo = getimagesizefromstring(Storage::disk('public')->get($photo->display_key));
        $thumbnailInfo = getimagesizefromstring(Storage::disk('public')->get($photo->thumbnail_key));
        $this->assertSame('image/webp', $displayInfo['mime']);
        $this->assertSame('image/webp', $thumbnailInfo['mime']);
        $this->assertSame(1200, $displayInfo[0]);
        $this->assertSame(600, $thumbnailInfo[0]);
    }

    public function test_required_processing_completion_can_publish_photo_automatically(): void
    {
        $this->configureCosDataWanxiang();
        $this->seed(GalleryTaxonomySeeder::class);
        $photo = Photo::query()->create([
            'title' => '处理完成自动发布测试',
            'status' => 'draft',
            'copyright_status' => 'credited',
            'publish_after_processing' => true,
            'original_key' => 'photos/originals/auto-publish.jpg',
        ]);
        $photo->categories()->sync(Category::query()->children()->where('name', '待补充')->pluck('id')->all());
        $wanxiang = Mockery::mock(TencentDataWanxiangService::class);
        $wanxiang->shouldReceive('generateDerivatives')
            ->once()
            ->andReturn([
                'display_key' => 'photos/derived/'.$photo->uuid.'/display.webp',
                'thumbnail_key' => 'photos/derived/'.$photo->uuid.'/thumbnail.webp',
            ]);
        $this->app->instance(TencentDataWanxiangService::class, $wanxiang);

        $processing = app(PhotoProcessingService::class);
        $processing->createJob($photo, 'metadata')->update(['status' => 'done', 'processed_at' => now()]);
        $processing->createJob($photo, 'hash')->update(['status' => 'done', 'processed_at' => now()]);
        $derivativeJob = $processing->createJob($photo, 'datawanxiang_derivatives');

        $processed = $processing->process($derivativeJob);

        $this->assertSame('done', $processed->status);
        $this->assertSame('published', $photo->refresh()->status);
        $this->assertFalse($photo->publish_after_processing);
        $this->assertNotNull($photo->published_at);
    }

    public function test_latest_successful_derivative_job_can_auto_publish_after_previous_failure(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $image = UploadedFile::fake()->image('retry-auto-publish.jpg', 800, 600);
        $originalKey = 'photos/originals/retry-auto-publish.jpg';
        Storage::disk('public')->put($originalKey, file_get_contents($image->getRealPath()));

        $photo = Photo::query()->create([
            'title' => '重试后自动发布测试',
            'status' => 'draft',
            'copyright_status' => 'credited',
            'publish_after_processing' => true,
            'original_key' => $originalKey,
        ]);
        $photo->categories()->sync(Category::query()->children()->where('name', '待补充')->pluck('id')->all());

        $processing = app(PhotoProcessingService::class);
        $processing->createJob($photo, 'metadata')->update(['status' => 'done', 'processed_at' => now()]);
        $processing->createJob($photo, 'hash')->update(['status' => 'done', 'processed_at' => now()]);
        $failed = $processing->createJob($photo, 'datawanxiang_derivatives');
        $failed->update(['status' => 'failed', 'error_message' => '第一次处理失败']);
        $successful = $processing->createJob($photo, 'datawanxiang_derivatives');

        $processed = $processing->process($successful);

        $this->assertSame('done', $processed->status);
        $this->assertSame('published', $photo->refresh()->status);
    }

    public function test_datawanxiang_processing_writes_display_and_thumbnail_keys(): void
    {
        $this->configureCosDataWanxiang();
        $photo = Photo::query()->create([
            'title' => '数据万象派生图测试',
            'original_key' => 'photos/originals/test.jpg',
        ]);
        $service = Mockery::mock(TencentDataWanxiangService::class);
        $service->shouldReceive('generateDerivatives')
            ->once()
            ->with(Mockery::on(fn (Photo $candidate): bool => $candidate->is($photo)))
            ->andReturn([
                'display_key' => 'photos/derived/'.$photo->uuid.'/display.webp',
                'thumbnail_key' => 'photos/derived/'.$photo->uuid.'/thumbnail.webp',
            ]);
        $this->app->instance(TencentDataWanxiangService::class, $service);

        $job = app(PhotoProcessingService::class)->createJob($photo, 'datawanxiang_derivatives');
        $processed = app(PhotoProcessingService::class)->process($job);

        $this->assertSame('done', $processed->status);
        $this->assertSame('photos/derived/'.$photo->uuid.'/display.webp', $photo->refresh()->display_key);
        $this->assertSame('photos/derived/'.$photo->uuid.'/thumbnail.webp', $photo->thumbnail_key);
    }

    public function test_datawanxiang_failure_is_recorded_and_manual_retry_can_succeed(): void
    {
        $this->configureCosDataWanxiang();
        $photo = Photo::query()->create([
            'title' => '数据万象失败重试测试',
            'original_key' => 'photos/originals/test.jpg',
        ]);
        $attempt = 0;
        $service = Mockery::mock(TencentDataWanxiangService::class);
        $service->shouldReceive('generateDerivatives')
            ->twice()
            ->with(Mockery::on(fn (Photo $candidate): bool => $candidate->is($photo)))
            ->andReturnUsing(function () use (&$attempt, $photo): array {
                $attempt++;

                if ($attempt === 1) {
                    throw new \RuntimeException('数据万象请求失败');
                }

                return [
                    'display_key' => 'photos/derived/'.$photo->uuid.'/display.webp',
                    'thumbnail_key' => 'photos/derived/'.$photo->uuid.'/thumbnail.webp',
                ];
            });
        $this->app->instance(TencentDataWanxiangService::class, $service);

        $job = app(PhotoProcessingService::class)->createJob($photo, 'datawanxiang_derivatives');
        $failed = app(PhotoProcessingService::class)->process($job);

        $this->assertSame('failed', $failed->status);
        $this->assertSame('数据万象请求失败', $failed->error_message);

        $retried = app(PhotoProcessingService::class)->retry($failed);

        $this->assertSame('done', $retried->status);
        $this->assertSame('photos/derived/'.$photo->uuid.'/display.webp', $photo->refresh()->display_key);
    }

    public function test_datawanxiang_service_builds_two_persistent_image_rules(): void
    {
        Setting::setValue('storage', 'config', [
            'mode' => 'cos',
            'cos' => [
                'secret_id' => 'test-secret-id',
                'secret_key' => encrypt('test-secret-key'),
                'region' => 'ap-guangzhou',
                'bucket' => 'testbucket-1250000000',
                'cdn_url' => '',
                'datawanxiang_enabled' => true,
            ],
        ]);
        $photo = Photo::query()->create([
            'title' => '数据万象 SDK 规则测试',
            'original_key' => 'photos/originals/test.jpg',
        ]);
        $displayKey = 'photos/derived/'.$photo->uuid.'/display.webp';
        $thumbnailKey = 'photos/derived/'.$photo->uuid.'/thumbnail.webp';
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('ImageProcess')
            ->once()
            ->with(Mockery::on(function (array $arguments) use ($photo, $displayKey, $thumbnailKey): bool {
                $operations = json_decode($arguments['PicOperations'], true);
                $fileIds = collect($operations['rules'] ?? [])->pluck('fileid')->all();

                return $arguments['Bucket'] === 'testbucket-1250000000'
                    && $arguments['Key'] === $photo->original_key
                    && $operations['is_pic_info'] === 1
                    && $fileIds === ['/'.$displayKey, '/'.$thumbnailKey];
            }))
            ->andReturn([
                'ProcessResults' => [
                    'Object' => [
                        ['Key' => $displayKey],
                        ['Key' => $thumbnailKey],
                    ],
                ],
            ]);

        $result = (new TencentDataWanxiangService(app(StorageSettings::class), $client))
            ->generateDerivatives($photo);

        $this->assertSame([
            'display_key' => $displayKey,
            'thumbnail_key' => $thumbnailKey,
        ], $result);
    }

    public function test_processing_hash_job_stores_analysis_result_and_duplicate_warning(): void
    {
        $service = app(PhotoProcessingService::class);
        Storage::disk('public')->put('duplicates/a.jpg', 'same-image-binary');
        Storage::disk('public')->put('duplicates/b.jpg', 'same-image-binary');

        $first = Photo::query()->create([
            'title' => '第一张重复测试图',
            'original_key' => 'duplicates/a.jpg',
        ]);
        $second = Photo::query()->create([
            'title' => '第二张重复测试图',
            'original_key' => 'duplicates/b.jpg',
        ]);

        $service->process($service->createJob($first, 'hash'));
        $service->process($service->createJob($second, 'hash'));

        $firstResult = $first->refresh()->analysisResult;
        $secondResult = $second->refresh()->analysisResult;

        $this->assertInstanceOf(PhotoAnalysisResult::class, $firstResult);
        $this->assertSame(hash('sha256', 'same-image-binary'), $firstResult->sha256_hash);
        $this->assertSame($firstResult->sha256_hash, $secondResult?->sha256_hash);
        $this->assertSame(1, $service->duplicateCount($first->refresh()));
        $this->assertSame('可能重复 1 张', $service->duplicateSummary($first->refresh()));
    }

    public function test_processing_job_fails_when_original_file_is_missing_and_can_retry(): void
    {
        $service = app(PhotoProcessingService::class);
        $photo = Photo::query()->create([
            'title' => '缺失原图测试',
            'original_key' => 'missing/photo.jpg',
        ]);
        $job = $service->createJob($photo, 'hash');

        $failed = $service->process($job);
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('原图文件不存在', (string) $failed->error_message);

        Storage::disk('public')->put('missing/photo.jpg', 'recovered-binary');
        $retried = $service->retry($failed);

        $this->assertSame('done', $retried->status);
        $this->assertSame(hash('sha256', 'recovered-binary'), $photo->refresh()->analysisResult?->sha256_hash);
    }

    public function test_ocr_processing_writes_text_and_manual_retry_can_succeed(): void
    {
        $photo = Photo::query()->create([
            'title' => 'OCR 测试图片',
            'original_key' => 'ocr/test.jpg',
        ]);
        $attempt = 0;
        $service = Mockery::mock(TencentDataWanxiangService::class);
        $service->shouldReceive('recognizeText')
            ->twice()
            ->with(Mockery::on(fn (Photo $candidate): bool => $candidate->is($photo)))
            ->andReturnUsing(function () use (&$attempt): string {
                $attempt++;

                if ($attempt === 1) {
                    throw new \RuntimeException('OCR 请求失败');
                }

                return "Messi\nBarcelona";
            });
        $this->app->instance(TencentDataWanxiangService::class, $service);

        $processing = app(PhotoProcessingService::class);
        $failed = $processing->process($processing->createJob($photo, 'ocr'));

        $this->assertSame('failed', $failed->status);
        $this->assertSame('OCR 请求失败', $failed->error_message);

        $retried = $processing->retry($failed);

        $this->assertSame('done', $retried->status);
        $this->assertSame("Messi\nBarcelona", $photo->refresh()->analysisResult?->ocr_text);
        app(PhotoProcessingService::class)->clearOcr($photo->refresh());

        $this->assertNull($photo->refresh()->analysisResult?->ocr_text);
    }

    public function test_datawanxiang_service_builds_ocr_request_and_extracts_text(): void
    {
        Setting::setValue('storage', 'config', [
            'mode' => 'cos',
            'cos' => [
                'secret_id' => 'test-secret-id',
                'secret_key' => encrypt('test-secret-key'),
                'region' => 'ap-guangzhou',
                'bucket' => 'testbucket-1250000000',
                'cdn_url' => '',
                'datawanxiang_enabled' => true,
            ],
        ]);
        $photo = Photo::query()->create([
            'title' => '数据万象 OCR SDK 测试',
            'original_key' => 'photos/originals/ocr.jpg',
        ]);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('opticalOcrRecognition')
            ->once()
            ->with(Mockery::on(function (array $arguments) use ($photo): bool {
                return $arguments === [
                    'Bucket' => 'testbucket-1250000000',
                    'Key' => $photo->original_key,
                    'Type' => 'general',
                    'LanguageType' => 'zh',
                ];
            }))
            ->andReturn([
                'TextDetections' => [
                    ['DetectedText' => 'Messi'],
                    ['DetectedText' => 'Barcelona'],
                ],
            ]);

        $text = (new TencentDataWanxiangService(app(StorageSettings::class), $client))
            ->recognizeText($photo);

        $this->assertSame("Messi\nBarcelona", $text);
    }

    public function test_similarity_processing_stores_perceptual_hash_and_candidate_review(): void
    {
        $image = UploadedFile::fake()->image('same-image.jpg', 32, 32);
        $contents = file_get_contents($image->getRealPath());

        Storage::disk('public')->put('similarity/source.jpg', $contents);
        Storage::disk('public')->put('similarity/candidate.jpg', $contents);

        $source = Photo::query()->create([
            'title' => '相似候选源图片',
            'original_key' => 'similarity/source.jpg',
        ]);
        $candidate = Photo::query()->create([
            'title' => '相似候选图片',
            'original_key' => 'similarity/candidate.jpg',
        ]);

        $service = app(PhotoProcessingService::class);
        $service->process($service->createJob($candidate, 'similarity'));
        $job = $service->process($service->createJob($source, 'similarity'));

        $this->assertSame('done', $job->status);
        $this->assertNotEmpty($source->refresh()->analysisResult?->perceptual_hash);
        $this->assertSame(1, PhotoSimilarityCandidate::query()->count());

        $similarity = PhotoSimilarityCandidate::query()->firstOrFail();
        $this->assertSame(0, $similarity->distance);
        $this->assertSame(100.0, $similarity->similarity_score);
        $this->assertSame('pending_review', $similarity->status);

        $reviewed = app(PhotoSimilarityService::class)->review(
            $similarity,
            'kept_separate',
            User::factory()->create(),
        );

        $this->assertSame('kept_separate', $reviewed->status);
        $this->assertNotNull($reviewed->reviewed_at);
        $this->assertNotNull($reviewed->reviewed_by);
    }

    public function test_similarity_processing_failure_is_recorded_and_can_retry(): void
    {
        $photo = Photo::query()->create([
            'title' => '相似图缺失原图测试',
            'original_key' => 'similarity/missing.jpg',
        ]);
        $service = app(PhotoProcessingService::class);
        $job = $service->process($service->createJob($photo, 'similarity'));

        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('原图文件不存在', (string) $job->error_message);

        $image = UploadedFile::fake()->image('recovered.jpg', 32, 32);
        Storage::disk('public')->put('similarity/missing.jpg', file_get_contents($image->getRealPath()));

        $retried = $service->retry($job);

        $this->assertSame('done', $retried->status);
        $this->assertNotEmpty($photo->refresh()->analysisResult?->perceptual_hash);
    }

    private function configureCosDataWanxiang(): void
    {
        Setting::setValue('storage', 'config', [
            'mode' => 'cos',
            'cos' => [
                'secret_id' => 'test-secret-id',
                'secret_key' => encrypt('test-secret-key'),
                'region' => 'ap-guangzhou',
                'bucket' => 'testbucket-1250000000',
                'cdn_url' => '',
                'datawanxiang_enabled' => true,
            ],
        ]);
    }

    public function test_admin_can_visit_similarity_candidate_resource(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->get(PhotoSimilarityCandidateResource::getUrl())
            ->assertOk();
    }

    public function test_admin_can_visit_processing_job_resource(): void
    {
        ProcessingJob::query()->create([
            'type' => 'metadata',
            'status' => 'pending',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->get(ProcessingJobResource::getUrl())->assertOk();
    }
}
