<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureFreshUserSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $sessionUserId = $request->session()->get('user_session_user_id');
        $currentVersion = max(1, (int) ($user?->session_version ?? 1));

        if ($user && (int) $sessionUserId === (int) $user->getKey() && $request->session()->has('user_session_version')) {
            if ((int) $request->session()->get('user_session_version') !== $currentVersion) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => '你的登录会话已被管理员撤销，请重新登录。',
                ]);
            }
        }

        if ($user) {
            $request->session()->put([
                'user_session_user_id' => (int) $user->getKey(),
                'user_session_version' => $currentVersion,
            ]);
        }

        return $next($request);
    }
}