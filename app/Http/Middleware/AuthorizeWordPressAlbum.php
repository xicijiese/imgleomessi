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
        $code = match (true) {
            ! preg_match('/^[a-f0-9]{64}$/i', $expected) => 'wordpress_not_configured',
            ! is_string($token) || $token === '' => 'wordpress_token_missing',
            strlen($token) < 32 || ! hash_equals(strtolower($expected), hash('sha256', $token)) => 'wordpress_token_mismatch',
            default => null,
        };
        if ($code !== null) {
            return response()->json(['message' => '相册接口未授权，请检查专用令牌。', 'code' => $code], 401)
                ->header('Cache-Control', 'private, no-store');
        }

        return $next($request)->header('Cache-Control', 'private, no-store');
    }
}
