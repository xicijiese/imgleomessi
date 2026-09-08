<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RejectsBannedUsers
{
    protected function rejectBannedUser(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isBanned()) {
            return null;
        }

        $message = '账号已被封禁，暂不能提交互动内容。';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 403);
        }

        abort(403, $message);
    }
}
