<?php

namespace App\Services;

use App\Jobs\ProcessPhotoAnalysisJob;
use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\ProcessingJob;
use Illuminate\Filesystem\FilesystemAdapter;
use RuntimeException;
use Throwable;

class PhotoProcessingService
{
    public function __construct(
        private readonly PhotoStorage $storage,
        private readonly LocalPhotoDerivativeService $localDerivatives,
        private readonly TencentDataWanxiangService $dataWanxiang,
        private readonly PhotoSimilarityService $similarity,
    ) {}

    /**
     * @return array<int, ProcessingJob>
     */
    public function createDefaultJobs(Photo $photo, bool $autoOcr = false): array
    {
        $types = ['metadata', 'hash', 'datawanxiang_derivatives'];

        if ($autoOcr && $this->dataWanxiang->enabled()) {
            $types[] = 'ocr';
        }

        $jobs = collect($types)
            ->map(fn (string $type): ProcessingJob => $this->createJob($photo, $type))
            ->all();

        foreach ($jobs as $job) {
            ProcessPhotoAnalysisJob::dispatch($job->id);
        }

        return $jobs;
    }

    public function createJob(Photo $photo, string $type, array $payload = []): ProcessingJob
    {
        if (! array_key_exists($type, ProcessingJob::TYPES)) {
            throw new RuntimeException('未知图片处理任务类型：'.$type);
        }

        return ProcessingJob::query()->firstOrCreate(
            [
                'photo_id' => $photo->id,
                'type' => $type,
                'status' => 'pending',
            ],
            [
                'payload' => $payload === [] ? null : $payload,
            ],
        );
    }

    public function queueDerivatives(Photo $photo): ProcessingJob
    {
        $activeJob = $photo->processingJobs()
            ->where('type', 'datawanxiang_derivatives')
            ->whereIn('status', ['pending', 'running'])
            ->latest('id')
            ->first();

        if ($activeJob instanceof ProcessingJob) {
            return $activeJob;
        }

        $job = $this->createJob($photo, 'datawanxiang_derivatives');
        ProcessPhotoAnalysisJob::dispatch($job->id);

        return $job;
    }
    public function queueOcr(Photo $photo): ProcessingJob
    {
        $job = $this->createJob($photo, 'ocr');
        ProcessPhotoAnalysisJob::dispatch($job->id);

        return $job;
    }
    public function queueSimilarity(Photo $photo): ProcessingJob
    {
        $job = $this->createJob($photo, 'similarity');
        ProcessPhotoAnalysisJob::dispatch($job->id);

        return $job;
    }

    public function process(ProcessingJob $job): ProcessingJob
    {
        $job->loadMissing('photo');

        if (! $job->photo instanceof Photo) {
            return $this->fail($job, '任务缺少关联图片。');
        }

        $job->update([
            'status' => 'running',
            'attempts' => $job->attempts + 1,
            'error_message' => null,
        ]);

        try {
            match ($job->type) {
                'metadata', 'hash' => $this->analyze($job->photo),
                'ocr_placeholder' => $this->markPlaceholder($job->photo),
                'datawanxiang_derivatives' => $this->processDerivatives($job->photo),
                'similarity' => $this->processSimilarity($job->photo),
                'ocr' => $this->processOcr($job->photo),
                default => throw new RuntimeException('未知图片处理任务类型：'.$job->type),
            };

            $job->update([
                'status' => 'done',
                'processed_at' => now(),
            ]);

            $this->publishAfterProcessingIfReady($job->photo->refresh());
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->fail($job, $throwable->getMessage());
        }

        return $job->refresh();
    }

    private function publishAfterProcessingIfReady(Photo $photo): void
    {
        if (! $photo->publish_after_processing || $photo->status !== 'draft') {
            return;
        }

        $jobs = $photo->processingJobs()
            ->whereIn('type', ['metadata', 'hash', 'datawanxiang_derivatives'])
            ->latest('id')
            ->get()
            ->unique('type')
            ->keyBy('type');

        foreach (['metadata', 'hash'] as $type) {
            if (($jobs->get($type)?->status ?? null) !== 'done') {
                return;
            }
        }

        if ($jobs->has('datawanxiang_derivatives')
            && (($jobs->get('datawanxiang_derivatives')?->status ?? null) !== 'done'
                || blank($photo->display_key)
                || blank($photo->thumbnail_key))) {
            return;
        }

        $photo->publish();
    }

    public function retry(ProcessingJob $job): ProcessingJob
    {
        $job->update([
            'status' => 'pending',
            'error_message' => null,
            'processed_at' => null,
        ]);

        return $this->process($job->refresh());
    }

    public function queueRetry(ProcessingJob $job): ProcessingJob
    {
        $job->update([
            'status' => 'pending',
            'error_message' => null,
            'processed_at' => null,
        ]);

        ProcessPhotoAnalysisJob::dispatch($job->id);

        return $job->refresh();
    }

    public function clearOcr(Photo $photo): void
    {
        PhotoAnalysisResult::query()->updateOrCreate(
            ['photo_id' => $photo->id],
            [
                'ocr_text' => null,
                'error_message' => null,
            ],
        );
    }
    public function duplicateCount(Photo $photo): int
    {
        $hash = $photo->analysisResult?->sha256_hash;

        if (blank($hash)) {
            return 0;
        }

        return PhotoAnalysisResult::query()
            ->where('sha256_hash', $hash)
            ->where('photo_id', '!=', $photo->id)
            ->count();
    }

    public function duplicateSummary(Photo $photo): string
    {
        $hash = $photo->analysisResult?->sha256_hash;

        if (blank($hash)) {
            return '未分析';
        }

        $count = $this->duplicateCount($photo);

        return $count > 0 ? '可能重复 '.$count.' 张' : '无重复';
    }

    private function processOcr(Photo $photo): void
    {
        $text = $this->dataWanxiang->recognizeText($photo);

        PhotoAnalysisResult::query()->updateOrCreate(
            ['photo_id' => $photo->id],
            [
                'ocr_text' => $text !== '' ? $text : null,
                'error_message' => null,
                'processed_at' => now(),
            ],
        );
    }
    private function processSimilarity(Photo $photo): void
    {
        $this->similarity->generateCandidates($photo);
    }

    private function processDerivatives(Photo $photo): void
    {
        $keys = $this->storage->activeDiskName() === 'cos'
            ? $this->dataWanxiang->generateDerivatives($photo)
            : $this->localDerivatives->generate($photo);

        $photo->update($keys);
    }

    private function analyze(Photo $photo): PhotoAnalysisResult
    {
        $key = $photo->original_key;

        if (blank($key)) {
            throw new RuntimeException('图片缺少原图存储 Key。');
        }        $storage = $this->storage->diskForKey($key);

        if (! $storage->exists($key)) {
            throw new RuntimeException('原图文件不存在：'.$key);
        }

        $path = $this->localPath($storage, $key);
        [$width, $height] = $this->imageSize($path);
        $mimeType = $this->mimeType($storage, $key, $path);
        $fileSize = $this->fileSize($storage, $key, $path);
        $sha256 = $this->sha256($storage, $key, $path);
        $exif = $this->exif($path);

        $result = PhotoAnalysisResult::query()->updateOrCreate(
            ['photo_id' => $photo->id],
            [
                'width' => $width ?? $photo->width,
                'height' => $height ?? $photo->height,
                'mime_type' => $mimeType ?? $photo->mime_type,
                'file_size' => $fileSize ?? $photo->file_size,
                'sha256_hash' => $sha256,
                'exif_json' => $exif,
                'error_message' => null,
                'processed_at' => now(),
            ],
        );

        $photo->update([
            'width' => $width ?? $photo->width,
            'height' => $height ?? $photo->height,
            'mime_type' => $mimeType ?? $photo->mime_type,
            'file_size' => $fileSize ?? $photo->file_size,
        ]);

        return $result;
    }

    private function markPlaceholder(Photo $photo): void
    {
        PhotoAnalysisResult::query()->updateOrCreate(
            ['photo_id' => $photo->id],
            ['ocr_text' => null, 'processed_at' => now()],
        );
    }

    private function fail(ProcessingJob $job, string $message): ProcessingJob
    {
        $job->update([
            'status' => 'failed',
            'error_message' => str($message)->limit(1000)->toString(),
        ]);

        return $job->refresh();
    }

    private function localPath(FilesystemAdapter $storage, string $key): ?string
    {
        try {
            $path = $storage->path($key);
        } catch (Throwable) {
            return null;
        }

        return is_file($path) ? $path : null;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function imageSize(?string $path): array
    {
        if ($path === null) {
            return [null, null];
        }

        $size = @getimagesize($path);

        if ($size === false) {
            return [null, null];
        }

        return [$size[0], $size[1]];
    }

    private function mimeType(FilesystemAdapter $storage, string $key, ?string $path): ?string
    {
        if ($path !== null) {
            return mime_content_type($path) ?: null;
        }

        try {
            return $storage->mimeType($key) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function fileSize(FilesystemAdapter $storage, string $key, ?string $path): ?int
    {
        if ($path !== null) {
            return filesize($path) ?: null;
        }

        try {
            return $storage->size($key) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function sha256(FilesystemAdapter $storage, string $key, ?string $path): string
    {
        if ($path !== null) {
            return hash_file('sha256', $path);
        }

        $stream = $storage->readStream($key);

        if (! is_resource($stream)) {
            throw new RuntimeException('无法读取原图文件流。');
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    private function exif(?string $path): ?array
    {
        if ($path === null || ! function_exists('exif_read_data')) {
            return null;
        }

        $data = @exif_read_data($path, null, true, false);

        if (! is_array($data) || $data === []) {
            return null;
        }

        return collect($data)
            ->map(fn (mixed $section): mixed => is_array($section) ? array_filter($section, fn (mixed $value): bool => is_scalar($value) || $value === null) : $section)
            ->filter(fn (mixed $section): bool => is_array($section) ? $section !== [] : is_scalar($section))
            ->all();
    }
}
