<?php

namespace App\Jobs;

use App\Models\PhotoAnalysisResult;
use App\Models\PhotoSubmission;
use App\Services\PhotoSimilarityService;
use App\Services\SubmissionStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzePhotoSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public string $submissionId) {}

    public function handle(SubmissionStorage $storage, PhotoSimilarityService $similarity): void
    {
        $row = PhotoSubmission::query()->find($this->submissionId);
        if ($row && ! $row->cleaned_at && in_array($row->status, ['temporary', 'pending'], true)) {
            $hash = $similarity->hashForContents($storage->contents($row));
            $matches = [];
            foreach (PhotoAnalysisResult::query()->whereNotNull('perceptual_hash')->cursor() as $candidate) {
                if (preg_match('/^[a-f0-9]{16}$/i', $candidate->perceptual_hash) && $similarity->hammingDistance($hash, $candidate->perceptual_hash) <= 12) {
                    $matches[] = $candidate->photo_id;
                    if (count($matches) >= 12) {
                        break;
                    }
                }
            }
            PhotoSubmission::query()->whereKey($row->id)->whereNull('cleaned_at')->update(['perceptual_hash' => $hash, 'similar_photo_ids' => $matches]);
        }
    }
}
