<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RejectsBannedUsers;
use App\Models\Photo;
use App\Models\PhotoFavorite;
use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PhotoFavoriteController extends Controller
{
    use RejectsBannedUsers;

    public function store(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        if ($response = $this->rejectBannedUser($request)) {
            return $response;
        }

        $photo = $this->publicPhoto($uuid);

        PhotoFavorite::query()->firstOrCreate([
            'photo_id' => $photo->id,
            'user_id' => $request->user()->id,
        ]);

        app(BadgeService::class)->evaluate($request->user());

        return $this->response($request, true);
    }

    public function destroy(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        if ($response = $this->rejectBannedUser($request)) {
            return $response;
        }

        $photo = $this->publicPhoto($uuid);

        PhotoFavorite::query()
            ->where('photo_id', $photo->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return $this->response($request, false);
    }

    private function response(Request $request, bool $isFavorited): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'is_favorited' => $isFavorited,
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
