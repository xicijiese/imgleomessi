<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RejectsBannedUsers;
use App\Models\Comment;
use App\Models\Report;
use App\Models\SensitiveWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentReportController extends Controller
{
    use RejectsBannedUsers;

    public function store(Request $request, int $comment): JsonResponse
    {
        if ($response = $this->rejectBannedUser($request)) {
            return $response;
        }

        $target = Comment::query()
            ->published()
            ->discussion()
            ->whereKey($comment)
            ->whereHas('photo', fn ($query) => $query
                ->published()
                ->whereNotIn('copyright_status', ['restricted', 'remove_requested']))
            ->firstOrFail();

        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(Report::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $scan = SensitiveWord::scan($data['details'] ?? null);

        Report::query()->create([
            'user_id' => $request->user()->id,
            'target_type' => 'comment',
            'target_id' => $target->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => 'pending',
            'risk_level' => $scan['risk_level'],
            'sensitive_word_hits' => $scan['hits'] === [] ? null : $scan['hits'],
        ]);

        return response()->json([
            'submitted' => true,
            'status' => 'pending',
            'message' => '举报已提交，等待处理。',
        ], 201);
    }
}
