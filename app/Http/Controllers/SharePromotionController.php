<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSharePoster;
use App\Models\Photo;
use App\Models\PhotoShare;
use App\Models\ShareLink;
use App\Models\ShareReferral;
use App\Services\QueueWorkerStatus;
use App\Services\SharePromotion;
use App\Services\UserCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SharePromotionController extends Controller
{
    public function create(Request $request, SharePromotion $service)
    {
        $data = $request->validate(['type' => ['required', Rule::in(['home', 'photo', 'album', 'topic'])], 'key' => 'required|string|max:180', 'channel' => ['required', Rule::in(SharePromotion::CHANNELS)]]);
        $link = $service->link($request->user(), $data['type'], $data['key'], $data['channel']);
        if ($request->user()) {
            $visitor = $request->cookie('share_visitor');
            if (! is_string($visitor) || ! Str::isUuid($visitor)) {
                $visitor = (string) Str::uuid();
            }
            cookie()->queue(cookie('share_visitor', $visitor, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax'));
            $hash = $request->session()->get('share_visitor_hash') ?: $service->hash('visitor:'.$visitor);
            $request->session()->put('share_visitor_hash', $hash);
            DB::table('share_known_users')->insertOrIgnore(['identity' => $hash, 'user_id' => $request->user()->id]);
        }

        return response()->json(['code' => $link->code, 'url' => $service->url($link), 'title' => $service->available($link)['title']])->header('Cache-Control', 'private, no-store');
    }

    public function operation(Request $request, string $code, SharePromotion $service)
    {
        $link = ShareLink::where('code', $code)->firstOrFail();
        $service->available($link);
        abort_if($request->user() && ! $service->enabledUser($request->user()), 403);
        abort_unless($link->user_id === $request->user()?->id, 403);
        // 操作日志不参与有效访客归因或发奖。
        $link->increment('operations');
        if ($link->target_type === 'photo') {
            $photo = Photo::where('uuid', $link->target_key)->firstOrFail();
            PhotoShare::create(['photo_id' => $photo->id, 'user_id' => $request->user()?->id, 'channel' => $link->channel, 'page_url' => $service->url($link), 'ip_address' => null, 'user_agent' => null]);
        }

        return response()->json(['recorded' => true]);
    }

    public function landing(Request $request, string $code, SharePromotion $service)
    {
        $link = ShareLink::where('code', $code)->firstOrFail();
        $target = $service->available($link);
        $request->session()->put('share_landing', [$code => true]);

        return redirect($target['path'].'?via='.$link->code)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex');
    }

    public function start(Request $request, SharePromotion $service)
    {
        $request->validate(['code' => 'required|string|max:32', 'path' => 'required|string|max:255']);
        $link = ShareLink::where('code', $request->input('code'))->firstOrFail();

        return response()->json($service->start($request, $link))->header('Cache-Control', 'private, no-store');
    }

    public function tick(Request $request, string $id, SharePromotion $service)
    {
        $request->validate(['receipt' => 'required|string|max:100', 'visible' => 'required|boolean']);

        return response()->json($service->tick($request, $id))->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request, UserCenter $center, SharePromotion $service)
    {
        $service->mergeLogin($request);

        return Inertia::render('Me/Shares', ['me' => [...$center->shell($request->user()), 'effective_count' => ShareReferral::where('user_id', $request->user()->id)->where('status', 'valid')->count()]]);
    }

    private function posterLink(Request $request, string $code, SharePromotion $service): ShareLink
    {
        $link = ShareLink::where('code', $code)->firstOrFail();
        $service->available($link);
        abort_unless($link->user_id === $request->user()?->id, 403);

        return $link;
    }

    public function poster(Request $request, string $code, SharePromotion $service)
    {
        $link = $this->posterLink($request, $code, $service);
        $target = $service->available($link);
        $fingerprint = GenerateSharePoster::fingerprint($target);
        DB::transaction(function () use ($link, $fingerprint) {
            $current = ShareLink::whereKey($link->id)->lockForUpdate()->firstOrFail();
            if ($current->poster_status === 'ready' && $current->poster_fingerprint === $fingerprint && Storage::disk('local')->exists($current->poster_path)) {
                return;
            }
            if ($current->poster_status === 'pending' && $current->poster_requested_at?->gt(now()->subMinutes(5))) {
                return;
            }
            $current->update(['poster_status' => 'pending', 'poster_error' => null, 'poster_requested_at' => now(), 'poster_fingerprint' => $fingerprint]);
            GenerateSharePoster::dispatch($current->id, $fingerprint)->onConnection(app(QueueWorkerStatus::class)->connection())->afterCommit();
        });

        return $this->posterStatus($request, $code, $service);
    }

    public function posterStatus(Request $request, string $code, SharePromotion $service)
    {
        $link = $this->posterLink($request, $code, $service);
        $valid = $link->poster_fingerprint === GenerateSharePoster::fingerprint($service->available($link));

        return response()->json(['status' => $valid ? $link->poster_status : 'none', 'error' => $link->poster_error, 'worker_available' => app(QueueWorkerStatus::class)->available(), 'image_url' => $valid && $link->poster_status === 'ready' ? '/sharing/'.$link->code.'/poster/image' : null])->header('Cache-Control', 'private, no-store');
    }

    public function posterImage(Request $request, string $code, SharePromotion $service)
    {
        $link = $this->posterLink($request, $code, $service);
        abort_unless($link->poster_status === 'ready' && $link->poster_fingerprint === GenerateSharePoster::fingerprint($service->available($link)), 404);
        abort_unless(Storage::disk('local')->exists($link->poster_path), 404);

        return Storage::disk('local')->response($link->poster_path, 'share-poster.png', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex']);
    }
}
