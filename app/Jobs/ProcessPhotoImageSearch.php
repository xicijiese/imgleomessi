<?php

namespace App\Jobs;

use App\Models\PhotoAnalysisResult;
use App\Models\PhotoImageSearch;
use App\Services\PhotoSimilarityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessPhotoImageSearch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $searchId)
    {
        $this->onConnection(config('queue.default') === 'sync' ? 'database' : config('queue.default'));
    }

    public function handle(PhotoSimilarityService $similarity): void
    {
        $search = PhotoImageSearch::query()->find($this->searchId);
        if (! $search || in_array($search->status, ['done', 'failed'], true)) {
            return;
        }
        $path = $search->temporary_path;
        try {
            if ($search->expires_at->isPast()) {
                $search->update(['status' => 'failed', 'error_message' => '查询已过期，请重新选择图片。']);

                return;
            }
            $search->update(['status' => 'running']);
            $hash = $similarity->hashForContents(Storage::disk('local')->get($path));
            $matches = [];
            PhotoAnalysisResult::query()->whereNotNull('perceptual_hash')
                ->whereHas('photo', fn ($query) => $query->published()->whereNotIn('copyright_status', ['restricted', 'remove_requested']))
                ->orderBy('photo_id')->each(function ($result) use ($hash, $similarity, &$matches): void {
                    if (! preg_match('/^[a-f0-9]{16}$/i', (string) $result->perceptual_hash)) {
                        return;
                    }
                    $distance = $similarity->hammingDistance($hash, $result->perceptual_hash);
                    if ($distance <= PhotoSimilarityService::DISTANCE_THRESHOLD) {
                        $matches[] = ['photo_id' => $result->photo_id, 'distance' => $distance];
                        usort($matches, fn ($a, $b) => [$a['distance'], $a['photo_id']] <=> [$b['distance'], $b['photo_id']]);
                        $matches = array_slice($matches, 0, 48);
                    }
                });
            $search->update(['status' => 'done', 'matches' => $matches]);
        } catch (Throwable $exception) {
            report($exception);
            $search->update(['status' => 'failed', 'error_message' => '暂时无法分析这张图片，请更换图片或稍后重试。']);
        } finally {
            if (! $path || ! Storage::disk('local')->exists($path) || Storage::disk('local')->delete($path)) {
                $search->update(['temporary_path' => null]);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $search = PhotoImageSearch::query()->find($this->searchId);
        if ($search) {
            $path = $search->temporary_path;
            if (! $path || ! Storage::disk('local')->exists($path) || Storage::disk('local')->delete($path)) {
                $search->temporary_path = null;
            }
            $search->fill(['status' => 'failed', 'error_message' => '查询处理失败，请重新选择图片。'])->save();
        }
    }
}
