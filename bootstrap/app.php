<?php

use App\Http\Middleware\AwardAnniversaryBadges;
use App\Http\Middleware\EnsureFreshUserSession;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MergeShareIdentity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/wordpress.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['payments/xunhu/notify']);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            EnsureFreshUserSession::class,
            AwardAnniversaryBadges::class,
            MergeShareIdentity::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            if (! in_array($status, [401, 403, 404, 405, 410, 419, 429, 500, 502, 503, 504], true)
                || ($request->expectsJson() && ! $request->header('X-Inertia'))
                || ($status >= 500 && config('app.debug'))) {
                return $response;
            }
            if ($request->header('X-Inertia')) {
                return Inertia::location($request->isMethod('GET')
                    ? $request->fullUrl() : url('/errors/'.$status.'.html'));
            }
            $headers = $response->headers->all();
            unset($headers['content-type'], $headers['content-length'], $headers['content-encoding']);

            return response()->view('errors.archive', ['status' => $status], $status, $headers);
        });
    })->create();
