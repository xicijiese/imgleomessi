<?php

namespace App\Jobs;

use App\Models\ProcessingJob;
use App\Services\PhotoProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class ProcessPhotoAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public readonly int $processingJobId) {}

    public function handle(PhotoProcessingService $service): void
    {
        $job = ProcessingJob::query()->find($this->processingJobId);

        if (! $job instanceof ProcessingJob || $job->status === 'done') {
            return;
        }

        $processed = $service->process($job);

        if ($processed->status === 'failed' && $this->attempts() < $this->tries) {
            throw new RuntimeException($processed->error_message ?? '数据万象处理失败。');
        }
    }
}
