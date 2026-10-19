<?php

namespace App\Services;

use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoDeduplicationLog;
use App\Models\PhotoSimilarityCandidate;
use App\Models\PhotoSimilarityGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PhotoSimilarityGovernance
{
    public function rebuildGroups(): int
    {
        return DB::transaction(function (): int {
            $edges = [];
            PhotoSimilarityCandidate::query()
                ->whereIn('status', ['pending_review', 'confirmed_duplicate'])
                ->where('distance', '<=', PhotoSimilarityService::DISTANCE_THRESHOLD)
                ->whereHas('photo', fn ($query) => $query->where('status', '!=', 'archived'))
                ->whereHas('candidatePhoto', fn ($query) => $query->where('status', '!=', 'archived'))
                ->orderBy('photo_id')->orderBy('candidate_photo_id')
                ->each(function ($candidate) use (&$edges): void {
                    $edges[$candidate->photo_id][$candidate->candidate_photo_id] = true;
                    $edges[$candidate->candidate_photo_id][$candidate->photo_id] = true;
                });

            // 完全连接分组，避免相似关系的传递性把不同画面串成一个大组。
            ksort($edges);
            $assigned = [];
            $groups = [];
            foreach ($edges as $anchor => $neighbors) {
                if (isset($assigned[$anchor])) {
                    continue;
                }
                $members = [$anchor];
                ksort($neighbors);
                foreach (array_keys($neighbors) as $id) {
                    if (isset($assigned[$id]) || count($members) >= 50) {
                        continue;
                    }
                    foreach ($members as $member) {
                        if (! isset($edges[$member][$id])) {
                            continue 2;
                        }
                    }
                    $members[] = $id;
                }
                if (count($members) > 1) {
                    $groups[] = $members;
                    foreach ($members as $id) {
                        $assigned[$id] = true;
                    }
                }
            }
            PhotoSimilarityGroup::query()->get()->each->delete();
            foreach ($groups as $members) {
                $group = PhotoSimilarityGroup::query()->create(['representative_photo_id' => $members[0]]);
                $group->photos()->attach($members);
            }

            return count($groups);
        });
    }

    public function separate(PhotoSimilarityGroup $group, int $photoId, User $operator): void
    {
        DB::transaction(function () use ($group, $photoId, $operator): void {
            $group = PhotoSimilarityGroup::query()->lockForUpdate()->findOrFail($group->id);
            abort_unless($group->photos()->where('photos.id', $photoId)->exists(), 422);
            foreach ($group->photos as $member) {
                if ($member->id === $photoId) {
                    continue;
                }
                PhotoSimilarityCandidate::query()->where('photo_id', min($photoId, $member->id))
                    ->where('candidate_photo_id', max($photoId, $member->id))->update([
                        'status' => 'kept_separate', 'reviewed_by' => $operator->id, 'reviewed_at' => now(),
                    ]);
            }
            $group->photos()->detach($photoId);
            if ($group->photos()->count() < 2) {
                $group->delete();
            } elseif ((int) $group->representative_photo_id === $photoId) {
                $group->update(['representative_photo_id' => $group->photos()->min('photos.id')]);
            }
        });
    }

    public function deduplicate(?int $operatorId): int
    {
        $count = 0;
        $hashes = PhotoAnalysisResult::query()->whereNotNull('sha256_hash')
            ->where('sha256_hash', '!=', '')->select('sha256_hash')
            ->groupBy('sha256_hash')->havingRaw('COUNT(*) > 1')->pluck('sha256_hash');
        foreach ($hashes as $hash) {
            if (! preg_match('/^[a-f0-9]{64}$/i', $hash)) {
                continue;
            }
            $count += DB::transaction(function () use ($hash, $operatorId): int {
                $ids = PhotoAnalysisResult::query()->where('sha256_hash', $hash)->orderBy('photo_id')
                    ->lockForUpdate()->pluck('photo_id');
                $photos = Photo::query()->whereIn('id', $ids)->whereIn('status', ['published', 'draft'])
                    ->whereNotIn('copyright_status', ['restricted', 'remove_requested'])
                    ->orderBy('id')->lockForUpdate()->get()
                    ->sortBy(fn ($photo) => [$photo->status === 'published' ? 0 : 1, $photo->id])->values();
                $retained = $photos->first();
                if (! $retained) {
                    return 0;
                }
                $archived = 0;
                foreach ($photos->skip(1) as $photo) {
                    // 人工恢复表示需要保留独立记录，后续批次不再次自动归档。
                    if (PhotoDeduplicationLog::query()->where('photo_id', $photo->id)->whereNotNull('restored_at')->exists()) {
                        continue;
                    }
                    PhotoDeduplicationLog::query()->create([
                        'photo_id' => $photo->id, 'retained_photo_id' => $retained->id,
                        'sha256_hash' => $hash, 'previous_status' => $photo->status, 'operated_by' => $operatorId,
                    ]);
                    $photo->archive();
                    $archived++;
                }

                return $archived;
            });
        }

        return $count;
    }

    public function restore(PhotoDeduplicationLog $log, User $operator): void
    {
        DB::transaction(function () use ($log, $operator): void {
            $log = PhotoDeduplicationLog::query()->lockForUpdate()->findOrFail($log->id);
            if ($log->restored_at !== null) {
                return;
            }
            $photo = Photo::query()->lockForUpdate()->findOrFail($log->photo_id);
            $photo->restoreFromArchive();
            $log->update(['restored_at' => now(), 'restored_by' => $operator->id]);
        });
    }
}
