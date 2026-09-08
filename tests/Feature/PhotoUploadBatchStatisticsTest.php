<?php

namespace Tests\Feature;

use App\Filament\Resources\PhotoUploadBatches\PhotoUploadBatchResource;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\ProcessingJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoUploadBatchStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_resource_exposes_processing_job_counts(): void
    {
        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'standalone',
            'status' => 'processing',
            'total_count' => 1,
        ]);
        $photo = Photo::query()->create([
            'title' => '批次统计测试',
            'photo_upload_batch_id' => $batch->id,
        ]);

        ProcessingJob::query()->create(['photo_id' => $photo->id, 'type' => 'metadata', 'status' => 'pending']);
        ProcessingJob::query()->create(['photo_id' => $photo->id, 'type' => 'hash', 'status' => 'running']);
        ProcessingJob::query()->create(['photo_id' => $photo->id, 'type' => 'labels', 'status' => 'failed']);

        $record = PhotoUploadBatchResource::getEloquentQuery()->findOrFail($batch->id);

        $this->assertSame(1, $record->pending_processing_jobs_count);
        $this->assertSame(1, $record->running_processing_jobs_count);
        $this->assertSame(1, $record->failed_processing_jobs_count);
    }
}