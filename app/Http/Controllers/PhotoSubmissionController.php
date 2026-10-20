<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Category;
use App\Models\PhotoSubmission;
use App\Models\SubmissionApplication;
use App\Models\SubmissionEvent;
use App\Models\Tag;
use App\Services\BadgeService;
use App\Services\PhotoSubmissionService;
use App\Services\SubmissionQualification;
use App\Services\SubmissionStorage;
use App\Services\UserCenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhotoSubmissionController extends Controller
{
    public function index(Request $request, UserCenter $center, BadgeService $badges)
    {
        $user = $request->user();
        $rows = PhotoSubmission::query()->where('user_id', $user->id)->whereNotIn('status', ['uploading', 'temporary'])
            ->with('photo')->latest()->paginate(20);

        $batchSummaries = PhotoSubmission::query()->where('user_id', $user->id)->whereIn('batch_id', $rows->getCollection()->pluck('batch_id'))
            ->whereNotNull('submitted_at')->selectRaw("batch_id, count(*) as total, sum(case when status = 'accepted' then 1 else 0 end) as accepted, sum(case when status = 'rejected' then 1 else 0 end) as rejected, sum(case when status in ('pending', 'processing') then 1 else 0 end) as waiting")
            ->groupBy('batch_id')->get();

        return Inertia::render('Me/Submissions', ['me' => [
            ...$center->shell($user), 'can_submit' => $user->canSubmitPhotos(), 'can_apply' => ($user->status ?? 'active') === 'active' && ($user->role ?? 'user') === 'user',
            'applications' => SubmissionApplication::query()->where('user_id', $user->id)->latest()->limit(20)->get()->map(fn ($row) => [
                'id' => $row->id, 'reason' => $row->reason, 'status' => $row->status,
                'status_label' => SubmissionApplication::STATUSES[$row->status], 'review_note' => $row->review_note,
                'created_at' => $row->created_at->timezone('Asia/Shanghai')->format('Y-m-d H:i'),
            ]),
            'batch_summaries' => $batchSummaries,
            'contribution_count' => $badges->contributionCount($user),
            'categories' => Category::query()->whereNull('parent_id')->where('visibility', 'public')->with(['children' => fn ($q) => $q->where('visibility', 'public')])->orderBy('sort_order')->get()->map(fn ($category) => [
                'id' => $category->id, 'name' => $category->name, 'children' => $category->children->map(fn ($item) => ['id' => $item->id, 'name' => $item->name]),
            ]),
            'albums' => Album::query()->published()->orderBy('title')->get(['id', 'title']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'submissions' => $rows->through(fn (PhotoSubmission $row): array => [
                'id' => $row->id, 'batch_id' => $row->batch_id, 'status' => $row->status, 'status_label' => PhotoSubmission::STATUSES[$row->status],
                'metadata' => $row->metadata, 'revision' => $row->revision, 'original_filename' => $row->original_filename,
                'contribution_revoked' => $row->contribution_revoked_at !== null,
                'contribution_note' => $row->contribution_revoked_at ? SubmissionEvent::query()->where('submission_id', $row->id)->where('action', 'contribution_revoked')->latest('id')->value('note') : null,
                'review_note' => $row->review_note, 'error_message' => $row->error_message, 'cleaned' => $row->cleaned_at !== null,
                'preview_url' => $row->cleaned_at ? null : '/me/submissions/'.$row->id.'/image',
                'cleanup_at' => $row->cleanup_at?->timezone('Asia/Shanghai')->format('Y-m-d H:i'),
                'created_at' => $row->created_at->timezone('Asia/Shanghai')->format('Y-m-d H:i'),
                'photo_url' => $row->photo?->status === 'published' && ! in_array($row->photo->copyright_status, ['restricted', 'remove_requested'], true) ? '/photos/'.$row->photo->uuid : null,
            ]),
        ]]);
    }

    public function apply(Request $request, SubmissionQualification $qualification)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $qualification->apply($request->user(), $data['reason']);

        return back(303)->with('status', 'submission-application-created');
    }

    public function upload(Request $request, PhotoSubmissionService $submissions)
    {
        abort_unless($request->user()->canSubmitPhotos(), 403);
        $data = $request->validate(['id' => ['required', 'uuid'], 'batch_id' => ['required', 'uuid'], 'file' => ['required', 'file', 'max:5120']]);
        $row = $submissions->upload($request->user(), $request->file('file'), $data['id'], $data['batch_id']);

        return response()->json(['id' => $row->id, 'preview_url' => '/me/submissions/'.$row->id.'/image']);
    }

    public function submit(Request $request, PhotoSubmissionService $submissions)
    {
        $data = $request->validate(['items' => ['required', 'array', 'min:1', 'max:20'], 'items.*.id' => ['required', 'uuid', 'distinct'], 'items.*.metadata' => ['required', 'array']]);
        $submissions->submit($request->user(), $data['items']);

        return back(303)->with('status', 'submission-created');
    }

    public function update(Request $request, PhotoSubmission $submission, PhotoSubmissionService $submissions)
    {
        abort_unless($submission->user_id === $request->user()->id, 403);
        $data = $request->validate(['metadata' => ['required', 'array'], 'revision' => ['required', 'integer']]);
        $submissions->edit($submission, $request->user(), $data['metadata'], $data['revision']);

        return back(303);
    }

    public function withdraw(Request $request, PhotoSubmission $submission, PhotoSubmissionService $submissions)
    {
        $submissions->withdraw($submission, $request->user());

        return back(303);
    }

    public function image(Request $request, PhotoSubmission $submission, SubmissionStorage $storage)
    {
        abort_unless($request->user()->id === $submission->user_id || $request->user()->canManageGalleryContent(), 403);
        abort_if($submission->cleaned_at || $submission->status === 'uploading', 404);

        return response($storage->contents($submission))->header('Content-Type', $submission->mime_type)
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Disposition', 'inline; filename="submission-image"');
    }
}
