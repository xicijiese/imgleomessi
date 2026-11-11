<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthorizeWordPressAlbum
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('wordpress.token_hash');
        $token = $request->bearerToken();
        if (! preg_match('/^[a-f0-9]{64}$/i', $expected) || ! is_string($token)
            || strlen($token) < 32 || ! hash_equals(strtolower($expected), hash('sha256', $token))) {
            return response()->json(['message' => '相册接口未授权，请检查专用令牌。'], 401)
                ->header('Cache-Control', 'private, no-store');
        }

        return $next($request)->header('Cache-Control', 'private, no-store');
    }
}
