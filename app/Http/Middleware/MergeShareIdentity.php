<?php

namespace App\Http\Middleware;

use App\Models\ShareLink;
use App\Services\SharePromotion;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MergeShareIdentity
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && ($request->session()->has('share_visitor_hash') || $request->cookie('share_visitor')) && ! $request->is('admin*', 'livewire*')) {
            $key = app(SharePromotion::class)->hash('user:'.$request->user()->id);
            if ($request->session()->get('share_merged_account') !== $key) {
                app(SharePromotion::class)->mergeLogin($request);
                $request->session()->put('share_merged_account', $key);
            }
        }
        $response = $next($request);
        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && preg_match('/^[a-zA-Z0-9]{24}$/', (string) $request->query('via', ''))) {
            $link = ShareLink::where('code', $request->query('via'))->first();
            if ($link) {
                try {
                    $target = app(SharePromotion::class)->available($link);
                    if ('/'.$request->path() === $target['path'] || ($request->path() === '/' && $target['path'] === '/')) {
                        $request->session()->put('share_landing', [$link->code => true]);
                        $response->headers->set('Cache-Control', 'private, no-store');
                    }
                } catch (HttpExceptionInterface|ModelNotFoundException $error) {
                    // 无效归因不影响当前公开页面浏览。
                }
            }
        }

        return $response;
    }
}
