<?php

namespace Tests\Feature;

use App\Jobs\ProcessPhotoAnalysisJob;
use App\Models\Photo;
use App\Models\ProcessingJob;
use App\Services\PhotoProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessingJobRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_processing_job_can_be_requeued_without_running_in_http_request(): void
    {
        Queue::fake();
        $photo = Photo::query()->create(['title' => '失败任务恢复测试']);
        $job = ProcessingJob::query()->create([
            'photo_id' => $photo->id,
            'type' => 'metadata',
            'status' => 'failed',
            'attempts' => 3,
            'error_message' => '原图暂时不可读',
        ]);

        $requeued = app(PhotoProcessingService::class)->queueRetry($job);

        $this->assertSame('pending', $requeued->status);
        $this->assertSame(3, $requeued->attempts);
        $this->assertNull($requeued->error_message);
        Queue::assertPushed(ProcessPhotoAnalysisJob::class, fn (ProcessPhotoAnalysisJob $queued): bool => $queued->processingJobId === $job->id);
    }
}
