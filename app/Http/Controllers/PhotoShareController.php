<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RejectsBannedUsers;
use App\Models\Photo;
use App\Models\PhotoShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PhotoShareController extends Controller
{
    use RejectsBannedUsers;

    public function store(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        if ($response = $this->rejectBannedUser($request)) {
            return $response;
        }

        $photo = $this->publicPhoto($uuid);
        $data = $request->validate([
            'channel' => ['nullable', 'string', Rule::in(['native_share', 'copy_link', 'weibo', 'wechat_qr'])],
            'page_url' => ['nullable', 'url', 'max:2048'],
        ]);

        PhotoShare::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $request->user()?->id,
            'channel' => $data['channel'] ?? 'copy_link',
            'page_url' => $data['page_url'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'recorded' => true,
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
