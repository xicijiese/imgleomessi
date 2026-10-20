<?php

namespace App\Services;

use App\Jobs\AnalyzePhotoSubmission;
use App\Jobs\ImportPhotoSubmission;
use App\Jobs\NotifySubmissionBatch;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoSubmission;
use App\Models\SubmissionEvent;
use App\Models\User;
use App\Notifications\UserCenterNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PhotoSubmissionService
{
    public function __construct(private readonly SubmissionStorage $storage) {}

    public function upload(User $user, UploadedFile $file, string $id, string $batch): PhotoSubmission
    {
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120', 'dimensions:max_width=12000,max_height=12000']])->validate();
        $size = @getimagesize($file->getRealPath());
        if (! $size || $size[0] * $size[1] > 40000000) {
            throw ValidationException::withMessages(['file' => '图片损坏或超过 4000 万像素。']);
        }
        $submission = DB::transaction(function () use ($user, $file, $id, $batch, $size): PhotoSubmission {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->canSubmitPhotos(), 403);
            $existing = PhotoSubmission::query()->find($id);
            if ($existing) {
                abort_unless($existing->user_id === $user->id, 403);
                abort_if($existing->status === 'uploading', 409, '该文件正在上传，请稍后重试。');
                if ($existing->status !== 'temporary' || $existing->batch_id !== $batch || $existing->cleaned_at) {
                    throw ValidationException::withMessages(['file' => '该次上传已失效，请重新选择图片。']);
                }

                return $existing;
            }
            $this->checkLimits($user, $batch, 1, true);
            $hash = hash_file('sha256', $file->getRealPath());
            if (PhotoSubmission::query()->where('batch_id', $batch)->where('user_id', $user->id)->where('sha256', $hash)->whereNotIn('status', ['rejected', 'withdrawn'])->exists()) {
                throw ValidationException::withMessages(['file' => '本批次已上传相同图片。']);
            }
            $location = $this->storage->location();

            return PhotoSubmission::query()->create([
                'id' => $id, 'batch_id' => $batch, 'user_id' => $user->id, 'status' => 'uploading',
                'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'file_size' => $file->getSize(), 'mime_type' => $file->getMimeType(), 'width' => $size[0], 'height' => $size[1],
                'sha256' => $hash, 'storage_driver' => $location['driver'], 'storage_location' => $location['location'],
                'temporary_key' => 'submissions/private/'.$id.'/original', 'cleanup_at' => now()->addDay(),
            ]);
        });
        if ($submission->status !== 'uploading') {
            return $submission;
        }
        try {
            $this->storage->put($submission, $file);
            $submission->update(['status' => 'temporary']);
            AnalyzePhotoSubmission::dispatch($submission->id)->onConnection(app(QueueWorkerStatus::class)->connection());
        } catch (\Throwable $error) {
            report($error);
            $submission->update(['status' => 'withdrawn', 'cleanup_at' => now(), 'error_message' => '上传失败，请重新上传；暂存文件将自动清理。']);
            throw ValidationException::withMessages(['file' => '投稿私有存储写入失败，请检查存储配置后重新上传。']);
        }

        return $submission->refresh();
    }

    private function checkLimits(User $user, string $batch, int $additional, bool $upload = false): void
    {
        $day = now('Asia/Shanghai')->startOfDay()->utc();
        $daily = PhotoSubmission::query()->where('user_id', $user->id)->where('submitted_at', '>=', $day)->count();
        $temporary = PhotoSubmission::query()->where('user_id', $user->id)->whereIn('status', ['uploading', 'temporary', 'withdrawn'])->whereNull('submitted_at')->whereNull('cleaned_at')->count();
        $batchCount = PhotoSubmission::query()->where('user_id', $user->id)->where('batch_id', $batch)->whereNotIn('status', ['withdrawn', 'rejected'])->count();
        if ($daily + ($upload ? $temporary : 0) + $additional > 100 || ($upload && $batchCount + $additional > 20)) {
            throw ValidationException::withMessages(['file' => '超过单批 20 张或每日 100 张限额，请稍后再试。']);
        }
    }

    public function metadata(array $data): array
    {
        $validated = Validator::make($data, [
            'title' => ['nullable', 'string', 'max:255'], 'event_date' => ['nullable', 'date_format:Y-m-d'],
            'source_url' => ['nullable', 'url:http,https', 'max:2000'], 'source_note' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'], 'show_credit' => ['required', 'boolean'],
            'album_id' => ['nullable', 'integer', Rule::exists('albums', 'id')->where('status', 'published')],
            'category_ids' => ['present', 'array', 'max:8'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where('visibility', 'public')->whereNotNull('parent_id')],
            'tag_ids' => ['present', 'array', 'max:30'], 'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
        ])->validate();
        $categories = Category::query()->whereIn('id', $validated['category_ids'])->get();
        if ($categories->pluck('parent_id')->unique()->count() !== $categories->count()) {
            throw ValidationException::withMessages(['category_ids' => '每个主分类只能选择一个子分类。']);
        }

        return $validated;
    }

    public function submit(User $user, array $items): void
    {
        $prepared = [];
        foreach ($items as $item) {
            $prepared[$item['id']] = $this->metadata($item['metadata']);
        }
        DB::transaction(function () use ($user, $prepared): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->canSubmitPhotos(), 403);
            $rows = PhotoSubmission::query()->where('user_id', $user->id)->whereIn('id', array_keys($prepared))->lockForUpdate()->get();
            abort_unless($rows->count() === count($prepared) && $rows->pluck('batch_id')->unique()->count() === 1, 422);
            $new = $rows->where('status', 'temporary')->count();
            if ($rows->contains(fn ($row) => ! in_array($row->status, ['temporary', 'pending'], true) || $row->cleaned_at || ($row->status === 'temporary' && $row->cleanup_at->isPast()))) {
                throw ValidationException::withMessages(['items' => '部分文件已过期或状态已变化，请刷新后重试。']);
            }
            $this->checkLimits($user, $rows->first()->batch_id, $new);
            foreach ($rows as $row) {
                if ($row->status === 'temporary') {
                    $meta = $prepared[$row->id];
                    $row->update(['status' => 'pending', 'metadata' => $meta, 'show_credit' => $meta['show_credit'], 'submitted_at' => now(), 'cleanup_at' => null]);
                    $this->event($row, $user, 'submitted', '提交投稿');
                }
            }
        });
    }

    public function edit(PhotoSubmission $submission, User $user, array $data, int $revision): void
    {
        $meta = $this->metadata($data);
        DB::transaction(function () use ($submission, $user, $meta, $revision): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->canSubmitPhotos(), 403);
            $row = PhotoSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_unless($row->user_id === $user->id, 403);
            $this->pending($row, $revision);
            $row->update(['metadata' => $meta, 'show_credit' => $meta['show_credit'], 'revision' => $row->revision + 1]);
            $this->event($row, $user, 'edited', '投稿人修改资料');
        });
    }

    public function withdraw(PhotoSubmission $submission, User $user): void
    {
        DB::transaction(function () use ($submission, $user): void {
            $row = PhotoSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_unless($row->user_id === $user->id, 403);
            if (! in_array($row->status, ['temporary', 'pending'], true)) {
                throw ValidationException::withMessages(['submission' => '当前状态不能撤回。']);
            }
            $row->update(['status' => 'withdrawn', 'cleanup_at' => now()->addDays(7), 'revision' => $row->revision + 1]);
            $this->event($row, $user, 'withdrawn', '投稿人撤回');
        });
    }

    public function review(PhotoSubmission $submission, User $operator, string $decision, string $note, int $revision, ?array $metadata = null, ?int $replacement = null): void
    {
        abort_unless($operator->fresh()->canManageGalleryContent(), 403);
        $meta = $metadata === null ? null : $this->metadata($metadata);
        DB::transaction(function () use ($submission, $operator, $decision, $note, $revision, $meta, $replacement): void {
            $row = PhotoSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            if ($row->status === 'failed' && $decision === 'rejected' && $row->revision === $revision) {
                DB::table('photo_submission_claims')->where('submission_id', $row->id)->delete();
            } else {
                $this->pending($row, $revision);
            }
            if (! in_array($decision, ['accepted', 'rejected'], true) || trim($note) === '') {
                throw ValidationException::withMessages(['note' => '请填写有效的审核决定和原因。']);
            }
            if ($meta !== null) {
                $row->metadata = $meta;
                $row->show_credit = $meta['show_credit'];
            }
            if ($decision === 'accepted') {
                $this->claim($row);
                if ($replacement) {
                    $target = Photo::query()->lockForUpdate()->findOrFail($replacement);
                    if ($target->width * $target->height >= $row->width * $row->height || in_array($target->copyright_status, ['restricted', 'remove_requested'], true) || $target->status === 'archived') {
                        throw ValidationException::withMessages(['replacement_photo_id' => '目标不可替换，或新图片分辨率没有改善。']);
                    }
                }
            }
            $row->fill(['status' => $decision === 'accepted' ? 'processing' : 'rejected', 'reviewed_by' => $operator->id,
                'reviewed_at' => now(), 'review_note' => $note, 'replacement_photo_id' => $replacement,
                'cleanup_at' => $decision === 'rejected' ? now()->addDays(7) : null, 'revision' => $row->revision + 1])->save();
            $this->event($row, $operator, 'review_'.$decision, $note);
            if ($decision === 'accepted') {
                ImportPhotoSubmission::dispatch($row->id)->onConnection(app(QueueWorkerStatus::class)->connection())->afterCommit();
            } else {
                NotifySubmissionBatch::dispatch($row->batch_id, $row->user_id)->onConnection(app(QueueWorkerStatus::class)->connection())->delay(now()->addSeconds(15))->afterCommit();
            }
        }, 3);
    }

    private function claim(PhotoSubmission $row): void
    {
        if (PhotoAnalysisResult::query()->where('sha256_hash', $row->sha256)->exists()) {
            throw ValidationException::withMessages(['note' => '完全相同的图片已存在图库，不能重复采纳。']);
        }
        DB::table('photo_submission_claims')->insertOrIgnore(['sha256' => $row->sha256, 'submission_id' => $row->id]);
        if (DB::table('photo_submission_claims')->where('sha256', $row->sha256)->value('submission_id') !== $row->id) {
            throw ValidationException::withMessages(['note' => '相同图片已有采纳任务，请勿重复采纳。']);
        }
    }

    public function retry(PhotoSubmission $row, User $operator): void
    {
        abort_unless($operator->fresh()->canManageGalleryContent(), 403);
        DB::transaction(function () use ($row, $operator): void {
            $row = PhotoSubmission::query()->lockForUpdate()->findOrFail($row->id);
            if ($row->status !== 'failed') {
                return;
            }
            $row->update(['status' => 'processing', 'reviewed_by' => $operator->id, 'error_message' => null]);
            $this->event($row, $operator, 'retried', '重试入库');
            ImportPhotoSubmission::dispatch($row->id)->onConnection(app(QueueWorkerStatus::class)->connection())->afterCommit();
        });
    }

    public function amend(PhotoSubmission $row, User $operator, array $data): PhotoSubmission
    {
        abort_unless($operator->fresh()->canManageGalleryContent(), 403);
        $meta = $this->metadata($data['metadata']);

        return DB::transaction(function () use ($row, $operator, $data, $meta): PhotoSubmission {
            $row = PhotoSubmission::query()->lockForUpdate()->findOrFail($row->id);
            $this->pending($row, (int) $data['revision']);
            $meta['show_credit'] = $row->show_credit;
            $row->update(['metadata' => $meta, 'show_credit' => $meta['show_credit'], 'internal_note' => $data['internal_note'] ?? null, 'revision' => $row->revision + 1]);
            $this->event($row, $operator, 'reviewer_edited', '审核人员整理资料');

            return $row;
        });
    }

    public function revokeContribution(PhotoSubmission $row, User $operator, string $note): void
    {
        abort_unless($operator->fresh()->isAdministrator(), 403);
        DB::transaction(function () use ($row, $operator, $note): void {
            $row = PhotoSubmission::query()->lockForUpdate()->findOrFail($row->id);
            if ($row->status !== 'accepted' || $row->contribution_revoked_at || trim($note) === '') {
                throw ValidationException::withMessages(['note' => '只有已采纳且未纠正的贡献才能撤销，必须填写原因。']);
            }
            $row->update(['contribution_revoked_at' => now()]);
            $this->event($row, $operator, 'contribution_revoked', $note);
            $row->user->notify(new UserCenterNotification('submission', '投稿贡献统计已调整', $note, '/me/submissions'));
        });
    }

    public function pending(PhotoSubmission $row, int $revision): void
    {
        if ($row->status !== 'pending' || $row->revision !== $revision) {
            throw ValidationException::withMessages(['revision' => '稿件已修改、撤回或处理，请刷新后重新审核。']);
        }
    }

    public function event(PhotoSubmission $row, ?User $operator, string $action, ?string $note = null, ?array $snapshot = null): void
    {
        SubmissionEvent::query()->create(['submission_id' => $row->id, 'operator_id' => $operator?->id, 'action' => $action,
            'note' => $note, 'snapshot' => $snapshot ?? ['status' => $row->status, 'metadata' => $row->metadata, 'revision' => $row->revision, 'replacement_photo_id' => $row->replacement_photo_id, 'photo_id' => $row->photo_id]]);
    }
}
