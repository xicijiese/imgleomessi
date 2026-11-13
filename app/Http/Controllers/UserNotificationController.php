<?php

namespace App\Http\Controllers;

use App\Services\UserCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class UserNotificationController extends Controller
{
    public function index(Request $request, UserCenter $center): Response
    {
        return Inertia::render('Me/Notifications', [
            'me' => $center->notifications($request->user(), $request->only(['status', 'type'])),
        ]);
    }

    public function open(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->ensureOwnNotification($request, $notification);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;
        // 只跳转站内路径，避免通知数据被用来跳转外部网站。
        if (! is_string($url) || ! str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\\\\\x00-\x20]/', $url)) {
            $url = '/me/notifications';
        }

        return redirect()->to($url, 303);
    }

    public function markRead(Request $request, DatabaseNotification $notification): JsonResponse|RedirectResponse
    {
        $this->ensureOwnNotification($request, $notification);

        $notification->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['read' => true]);
        }

        return back(status: 303);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['read' => true]);
        }

        return back(status: 303);
    }

    private function ensureOwnNotification(Request $request, DatabaseNotification $notification): void
    {
        $user = $request->user();

        abort_unless(
            $notification->notifiable_type === $user->getMorphClass()
            && (int) $notification->notifiable_id === $user->id,
            404,
        );
    }
}
