<?php

namespace App\Http\Middleware;

use App\Services\BadgeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AwardAnniversaryBadges
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethod('GET') && $request->user() && $response->getStatusCode() < 400 && ! $request->is('admin*', 'livewire*')) {
            app(BadgeService::class)->evaluateAnniversaries($request->user());
        }

        return $response;
    }
}
