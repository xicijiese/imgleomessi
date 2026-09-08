<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RejectsBannedUsers;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\SensitiveWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PhotoCommentController extends Controller
{
    use RejectsBannedUsers;

    public function storeDiscussion(Request $request, string $uuid): JsonResponse
    {
        if ($response = $this->rejectBannedUser($request)) {
            return $response;
        }

        $photo = $this->publicPhoto($uuid);
        $data = $request->validate([
            'content' => ['required', 'string', 'min:2', 'max:1000'],
        ]);
        $scan = SensitiveWord::scan($data['content']);

        $comment = Comment::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $request->user()->id,
            'type' => 'discussion',
            'content' => $data['content'],
            'status' => 'pending',
            'risk_level' => $scan['risk_level'],
            'sensitive_word_hits' => $scan['hits'] === [] ? null : $scan['hits'],
        ]);

        return response()->json([
            'submitted' => true,
            'status' => $comment->status,
            'message' => '评论已提交，等待审核。',
        ], 201);
    }

    public function storeCorrection(Request $request, string $uuid): JsonResponse
    {
        if ($response = $this->rejectBannedUser($request)) {
            return $response;
        }

        $photo = $this->publicPhoto($uuid);
        $data = $request->validate([
            'correction_field' => ['nullable', 'string', Rule::in(array_keys(Comment::CORRECTION_FIELDS))],
            'suggested_value' => ['nullable', 'string', 'max:1000'],
            'evidence_url' => ['nullable', 'url', 'max:2048'],
            'content' => ['required', 'string', 'min:2', 'max:1000'],
        ]);
        $scan = SensitiveWord::scan($data['content'], $data['suggested_value'] ?? null);

        $comment = Comment::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $request->user()->id,
            'type' => 'correction',
            'content' => $data['content'],
            'status' => 'pending',
            'correction_field' => $data['correction_field'] ?? null,
            'suggested_value' => $data['suggested_value'] ?? null,
            'evidence_url' => $data['evidence_url'] ?? null,
            'risk_level' => $scan['risk_level'],
            'sensitive_word_hits' => $scan['hits'] === [] ? null : $scan['hits'],
        ]);

        return response()->json([
            'submitted' => true,
            'status' => $comment->status,
            'message' => '补充 / 纠错已提交，等待审核。',
        ], 201);
    }

    private function publicPhoto(string $uuid): Photo
    {
        return Photo::query()
            ->published()
            ->whereNotIn('copyright_status', ['restricted', 'remove_requested'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
