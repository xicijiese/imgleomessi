<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Photo;
use App\Models\PhotoDeduplicationLog;
use App\Models\PhotoSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhotoDeletionService
{
    public function delete(Photo $record): bool
    {
        return DB::transaction(function () use ($record): bool {
            $photo = Photo::query()->lockForUpdate()->findOrFail($record->id);
            if (PhotoDeduplicationLog::where('photo_id', $photo->id)->orWhere('retained_photo_id', $photo->id)->exists()) {
                throw ValidationException::withMessages(['photo' => '关联受保护的查重处理历史，请使用归档。']);
            }
            $submissions = PhotoSubmission::where(fn ($query) => $query->where('photo_id', $photo->id)->orWhere('replacement_photo_id', $photo->id))->lockForUpdate()->get();
            if ($submissions->contains('status', 'processing')) {
                throw ValidationException::withMessages(['photo' => '图片关联的投稿正在入库，请等待处理结束后再删除。']);
            }
            foreach ($submissions as $submission) {
                // 保留审核与贡献历史，只解除指向已删除档案的关联。
                app(PhotoSubmissionService::class)->event($submission, auth()->user(), 'photo_deleted', '关联图片已由管理员删除', [
                    'deleted_photo_id' => $photo->id, 'deleted_photo_title' => $photo->title,
                    'photo_id' => $submission->photo_id, 'replacement_photo_id' => $submission->replacement_photo_id,
                ]);
                $submission->update([
                    'photo_id' => $submission->photo_id === $photo->id ? null : $submission->photo_id,
                    'replacement_photo_id' => $submission->replacement_photo_id === $photo->id ? null : $submission->replacement_photo_id,
                ]);
                if ($submission->photo_id === null) {
                    DB::table('photo_submission_claims')->where('submission_id', $submission->id)->delete();
                }
            }
            if (! $photo->delete()) {
                throw new \RuntimeException('删除未完成');
            }
            $photo->albums()->detach();
            Album::where('cover_photo_id', $photo->id)->update(['cover_photo_id' => null]);

            return true;
        }, 3);
    }
}
