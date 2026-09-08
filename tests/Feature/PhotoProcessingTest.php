<?php

namespace Tests\Feature;

use App\Filament\Resources\ProcessingJobs\ProcessingJobResource;
use App\Jobs\ProcessPhotoAnalysisJob;
use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoSimilarityCandidate;
use App\Models\ProcessingJob;
use App\Models\Setting;
use App\Models\User;

use App\Services\PhotoProcessingService;
use App\Services\PhotoSimilarityService;
use App\Services\PhotoUploadService;
use App\Services\TencentDataWanxiangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Qcloud\Cos\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

        $this->assertSame(2, $photo->processingJobs()->count());
        $this->assertTrue($photo->processingJobs()->where('type', 'metadata')->where('status', 'pending')->exists());
        $this->assertTrue($photo->processingJobs()->where('type', 'hash')->where('status', 'pending')->exists());
        Queue::assertPushed(ProcessPhotoAnalysisJob::class, 2);
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

    public function test_upload_processing_options_can_queue_ocr_and_labels(): void
    {
        $photo = Photo::query()->create([
            'title' => '自动识别任务测试',
            'original_key' => 'photos/originals/auto.jpg',
        ]);
        $wanxiang = Mockery::mock(TencentDataWanxiangService::class);
        $wanxiang->shouldReceive('enabled')->once()->andReturn(true);
        $this->app->instance(TencentDataWanxiangService::class, $wanxiang);

        $jobs = app(PhotoProcessingService::class)->createDefaultJobs($photo, true, true);

        $this->assertSame(['metadata', 'hash', 'datawanxiang_derivatives', 'ocr', 'labels'], collect($jobs)->map->type->all());
        $this->assertSame(5, $photo->processingJobs()->count());
        Queue::assertPushed(ProcessPhotoAnalysisJob::class, 5);
    }

    public function test_required_processing_completion_can_publish_photo_automatically(): void
    {
        $this->seed(\Database\Seeders\GalleryTaxonomySeeder::class);
        $photo = Photo::query()->create([
            'title' => '处理完成自动发布测试',
            'status' => 'draft',
            'copyright_status' => 'credited',
            'publish_after_processing' => true,
            'original_key' => 'photos/originals/auto-publish.jpg',
        ]);
        $photo->categories()->sync(\App\Models\Category::query()->children()->where('name', '待补充')->pluck('id')->all());
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
    public function test_datawanxiang_processing_writes_display_and_thumbnail_keys(): void
    {
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

        $result = (new TencentDataWanxiangService(app(\App\Services\StorageSettings::class), $client))
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

        $text = (new TencentDataWanxiangService(app(\App\Services\StorageSettings::class), $client))
            ->recognizeText($photo);

        $this->assertSame("Messi\nBarcelona", $text);
    }
    public function test_labels_processing_writes_labels_and_manual_retry_can_succeed(): void
    {
        $photo = Photo::query()->create([
            'title' => '智能标签测试图片',
            'original_key' => 'labels/test.jpg',
        ]);
        $attempt = 0;
        $service = Mockery::mock(TencentDataWanxiangService::class);
        $service->shouldReceive('recognizeLabels')
            ->twice()
            ->with(Mockery::on(fn (Photo $candidate): bool => $candidate->is($photo)))
            ->andReturnUsing(function () use (&$attempt): array {
                $attempt++;

                if ($attempt === 1) {
                    throw new \RuntimeException('标签请求失败');
                }

                return [[
                    'name' => 'football',
                    'confidence' => 96,
                    'first_category' => '运动',
                    'second_category' => '足球',
                ]];
            });
        $this->app->instance(TencentDataWanxiangService::class, $service);

        $processing = app(PhotoProcessingService::class);
        $failed = $processing->process($processing->createJob($photo, 'labels'));

        $this->assertSame('failed', $failed->status);
        $this->assertSame('标签请求失败', $failed->error_message);

        $retried = $processing->retry($failed);

        $this->assertSame('done', $retried->status);
        $this->assertSame([[
            'name' => 'football',
            'confidence' => 96,
            'first_category' => '运动',
            'second_category' => '足球',
        ]], $photo->refresh()->analysisResult?->ci_labels_json);

        app(PhotoProcessingService::class)->clearLabels($photo->refresh());

        $this->assertNull($photo->refresh()->analysisResult?->ci_labels_json);
    }

    public function test_datawanxiang_service_builds_label_request_and_extracts_labels(): void
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
            'title' => '数据万象标签 SDK 测试',
            'original_key' => 'photos/originals/labels.jpg',
        ]);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('detectLabelProcess')
            ->once()
            ->with(Mockery::on(function (array $arguments) use ($photo): bool {
                return $arguments === [
                    'Bucket' => 'testbucket-1250000000',
                    'Key' => $photo->original_key,
                    'Scenes' => '',
                ];
            }))
            ->andReturn([
                'Labels' => [
                    [
                        'Name' => 'football',
                        'Confidence' => 96,
                        'FirstCategory' => '运动',
                        'SecondCategory' => '足球',
                    ],
                ],
            ]);

        $labels = (new TencentDataWanxiangService(app(\App\Services\StorageSettings::class), $client))
            ->recognizeLabels($photo);

        $this->assertSame([[
            'name' => 'football',
            'confidence' => 96,
            'first_category' => '运动',
            'second_category' => '足球',
        ]], $labels);
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

    public function test_admin_can_visit_similarity_candidate_resource(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(\App\Filament\Resources\PhotoSimilarityCandidates\PhotoSimilarityCandidateResource::getUrl())
            ->assertOk();
    }
    public function test_admin_can_visit_processing_job_resource(): void
    {
        ProcessingJob::query()->create([
            'type' => 'metadata',
            'status' => 'pending',
        ]);

        $this->actingAs(User::factory()->create());

        $this->get(ProcessingJobResource::getUrl())->assertOk();
    }
}
