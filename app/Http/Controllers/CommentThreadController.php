<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RejectsBannedUsers;
use App\Models\Comment;
use App\Models\Photo;
use App\Services\PublicCommentThreads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentThreadController extends Controller
{
    use RejectsBannedUsers;

    public function index(Request $request, string $uuid, PublicCommentThreads $threads)
    {
        $request->validate(['parent' => ['nullable','integer','min:1'], 'comment_page' => ['nullable','integer','min:1','max:100000'], 'reply_page' => ['nullable','integer','min:1','max:100000']]);
        $photo = Photo::published()->whereNotIn('copyright_status', ['restricted', 'remove_requested'])->where('uuid', $uuid)->firstOrFail();
        $root = null;
        if ($request->filled('parent')) {
            $root = $threads->visible(Comment::query())->where('photo_id', $photo->id)->whereNull('parent_id')->findOrFail($request->integer('parent'));
        }

        return response()->json($threads->page($photo, $request, $root));
    }

    public function like(Request $request, int $comment, PublicCommentThreads $threads)
    {
        if ($response = $this->rejectBannedUser($request)) {
            return $response;
        }

        return DB::transaction(function () use ($request, $comment, $threads) {
            $c = $threads->visible(Comment::query())->whereKey($comment)->lockForUpdate()->firstOrFail();
            $q = DB::table('comment_likes')->where('comment_id', $c->id)->where('user_id', $request->user()->id);
            if ($request->isMethod('delete')) {
                $q->delete();
            } else {
                DB::table('comment_likes')->insertOrIgnore(['comment_id' => $c->id, 'user_id' => $request->user()->id, 'created_at' => now()]);
            }
            $count = DB::table('comment_likes')->where('comment_id', $c->id)->count();
            $c->update(['like_count' => $count]);

            return response()->json(['liked' => ! $request->isMethod('delete'), 'like_count' => $count]);
        });
    }
}
