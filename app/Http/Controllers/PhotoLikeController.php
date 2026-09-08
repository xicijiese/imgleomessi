<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RejectsBannedUsers;
use App\Models\Photo;
use App\Models\PhotoLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PhotoLikeController extends Controller
{
    use RejectsBannedUsers;

    public function store(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        if ($response = $this->rejectBannedUser($request)) {
            return $response;
        }

        $photo = $this->publicPhoto($uuid);

        PhotoLike::query()->firstOrCreate([
            'photo_id' => $photo->id,
            'user_id' => $request->user()->id,
        ]);

        return $this->response($request, $photo, true);
    }

    public function destroy(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        if ($response = $this->rejectBannedUser($request)) {
            return $response;
        }

        $photo = $this->publicPhoto($uuid);

        PhotoLike::query()
            ->where('photo_id', $photo->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return $this->response($request, $photo, false);
    }

    private function response(Request $request, Photo $photo, bool $isLiked): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'is_liked' => $isLiked,
                'likes_count' => $photo->likes()->count(),
            ]);
        }

        return back(status: 303);
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
