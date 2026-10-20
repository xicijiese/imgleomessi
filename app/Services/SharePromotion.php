<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\ShareEvent;
use App\Models\ShareLink;
use App\Models\ShareReferral;
use App\Models\ShareVisit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SharePromotion
{
    public const CHANNELS = ['copy_link', 'native_share', 'weibo', 'wechat_qr', 'poster'];

    private function sessionOwner(Request $request): string
    {
        if (! $request->session()->has('share_session_owner')) {
            $request->session()->put('share_session_owner', (string) Str::uuid());
        }

        return $this->hash($request->session()->get('share_session_owner'));
    }

    public function url(ShareLink $link): string
    {
        return rtrim(config('app.url'), '/').'/s/'.$link->code;
    }

    public function hash(string $value): string
    {
        return hash_hmac('sha256', $value, config('app.key'));
    }

    public function enabledUser(?User $user): bool
    {
        return $user && ($user->status ?? 'active') === 'active' && ! $user->isBanned();
    }

    private function publicPhotos($query)
    {
        return $query->where('status', 'published')->whereNotIn('copyright_status', ['restricted', 'remove_requested']);
    }

    public function target(string $type, string $key): array
    {
        $settings = app(HomepageSettings::class)->formState();
        $defaults = Setting::value('sharing', 'defaults', []);
        $site = $settings['site']['name'];
        $image = null;
        $source = '图片仅用于档案展示，不代表拥有版权';
        if ($type === 'home' && $key === 'home') {
            $title = $defaults['title'] ?? $site;
            $description = $defaults['description'] ?? '一起整理、发现与保存梅西的影像记忆';
            $path = '/';
            $image = $defaults['cover_path'] ?? null;
        } elseif ($type === 'photo') {
            $photo = $this->publicPhotos(Photo::query())->where('uuid', $key)->firstOrFail();
            $title = $photo->title;
            $description = $photo->description;
            $path = '/photos/'.$photo->uuid;
            $image = $photo->display_key;
        } elseif ($type === 'album') {
            $album = Album::published()->where('slug', $key)->whereHas('photos', fn ($q) => $this->publicPhotos($q))->firstOrFail();
            $photo = $this->publicPhotos($album->photos())->orderByRaw('photos.id = ? desc', [$album->cover_photo_id ?? 0])->orderBy('photos.id')->first();
            $title = $album->title;
            $description = $album->description;
            $path = '/albums/'.$album->slug;
            $image = $photo?->display_key;
        } elseif ($type === 'topic') {
            $topic = collect($settings['topic_module']['items'] ?? [])->first(fn ($item) => ($item['enabled'] ?? true) && filled($item['title'] ?? null) && ($item['url'] ?? '') === '/topics/'.$key);
            abort_unless($topic, 404);
            $title = $topic['title'];
            $description = $topic['description'] ?? '';
            $path = '/topics/'.$key;
            $photo = $this->publicPhotos(Photo::query())->whereIn('id', $topic['photo_ids'] ?? [])->first();
            $image = $photo?->display_key ?: ($topic['cover_image_path'] ?? null);
        } else {
            abort(404);
        }
        // 只允许服务端管理的相对存储 Key，海报生成不请求任意外部 URL。
        if ($image && (str_contains($image, '://') || str_contains($image, '..') || str_starts_with($image, '/'))) {
            $image = null;
        }

        return compact('title', 'description', 'path', 'image', 'site', 'source');
    }

    public function available(ShareLink $link): array
    {
        abort_unless($link->is_active, 410, '分享链接已停用');
        if ($link->user_id) {
            abort_unless($this->enabledUser($link->user), 410, '分享链接暂不可用');
        }

        return $this->target($link->target_type, $link->target_key);
    }

    public function link(?User $user, string $type, string $key, string $channel): ShareLink
    {
        $this->target($type, $key);
        abort_if($user && ! $this->enabledUser($user), 403);
        $reuse = $this->hash(json_encode([$user?->id, $type, $key, $channel]));
        $link = ShareLink::firstOrCreate(['reuse_key' => $reuse], ['code' => Str::random(24), 'user_id' => $user?->id, 'target_type' => $type, 'target_key' => $key, 'channel' => $channel]);
        $this->available($link);

        return $link;
    }

    public function start(Request $request, ShareLink $link): array
    {
        $target = $this->available($link);
        abort_unless($request->session()->get('share_landing.'.$link->code) && $request->string('path')->toString() === $target['path'], 403);
        abort_if(preg_match('/bot|spider|crawler|preview|Headless|facebookexternalhit/i', $request->userAgent() ?? ''), 403);
        $visitor = $request->cookie('share_visitor');
        if (! is_string($visitor) || ! Str::isUuid($visitor)) {
            $visitor = (string) Str::uuid();
        }
        cookie()->queue(cookie('share_visitor', $visitor, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax'));
        $visitorHash = $request->session()->get('share_visitor_hash') ?: $this->hash('visitor:'.$visitor);
        $sessionHash = $this->sessionOwner($request);
        $request->session()->put('share_visitor_hash', $visitorHash);
        $rateKey = 'share-start:'.$this->hash($request->ip() ?? '');
        abort_if(RateLimiter::tooManyAttempts($rateKey, 60), 429);
        RateLimiter::hit($rateKey, 60);
        $receipt = Str::random(48);

        return DB::transaction(function () use ($request, $link, $visitorHash, $sessionHash, $receipt) {
            $link = ShareLink::whereKey($link->id)->lockForUpdate()->firstOrFail();
            $unique = DB::table('share_link_audiences')->insertOrIgnore(['share_link_id' => $link->id, 'identity' => $visitorHash]);
            if ($request->user()) {
                DB::table('share_known_users')->insertOrIgnore(['identity' => $visitorHash, 'user_id' => $request->user()->id]);
            }
            $link->increment('visits');
            if ($unique) {
                $link->increment('unique_visitors');
            }
            $visit = ShareVisit::create(['id' => (string) Str::uuid(), 'share_link_id' => $link->id, 'visitor_hash' => $visitorHash, 'account_hash' => $request->user() ? $this->hash('user:'.$request->user()->id) : null, 'session_hash' => $sessionHash, 'ip_hash' => $this->hash($request->ip() ?? ''), 'receipt_hash' => $this->hash($receipt), 'last_tick_at' => now()]);

            return ['id' => $visit->id, 'receipt' => $receipt];
        });
    }

    public function tick(Request $request, string $id): array
    {
        return DB::transaction(function () use ($request, $id) {
            $visit = ShareVisit::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($visit->session_hash, $this->sessionOwner($request)), 403);
            abort_unless(hash_equals($visit->receipt_hash, $this->hash((string) $request->input('receipt'))), 409);
            abort_if($visit->created_at->lt(now()->subMinutes(30)), 410);
            $link = ShareLink::whereKey($visit->share_link_id)->lockForUpdate()->firstOrFail();
            if ($link->user_id) {
                // 在任何普通读取前锁定推广者，避免 MySQL 快照使并发临界授勋漏算。
                $link->setRelation('user', User::whereKey($link->user_id)->lockForUpdate()->firstOrFail());
            }
            $visit->setRelation('link', $link);
            $this->available($link);
            if ($visit->status !== 'pending') {
                return ['done' => true, 'status' => $visit->status];
            }
            $elapsed = max(0, $visit->last_tick_at->diffInSeconds(now(), false));
            // 超过节拍间隔的时间不计入，避免后台挂起或一次性提交十秒。
            $seconds = $request->boolean('visible') && $elapsed <= 4 ? min(2, (int) $elapsed) : 0;
            $receipt = Str::random(48);
            $visit->visible_seconds += $seconds;
            $visit->last_tick_at = now();
            $visit->receipt_hash = $this->hash($receipt);
            $visit->save();
            if ($visit->visible_seconds >= 10) {
                $self = $request->user()?->id === $link->user_id || ($link->user_id && DB::table('share_known_users')->where('identity', $visit->visitor_hash)->where('user_id', $link->user_id)->exists());
                if (! $link->user_id || $self || ($request->user() && ! $this->enabledUser($request->user()))) {
                    $visit->update(['status' => 'excluded', 'reason' => $self ? '本人访问' : '普通分享或账号状态受限']);
                } else {
                    $this->attribute($visit, $request->user());
                }
            }

            return ['done' => $visit->status !== 'pending', 'status' => $visit->status, 'receipt' => $receipt, 'seconds' => $visit->visible_seconds];
        }, 3);
    }

    public function attribute(ShareVisit $visit, ?User $visitor): void
    {
        $keys = [$visit->visitor_hash];
        $keys = array_merge($keys, DB::table('share_known_users')->where('identity', $visit->visitor_hash)->pluck('user_id')->map(fn ($id) => $this->hash('user:'.$id))->all());
        if ($visit->account_hash) {
            $keys[] = $visit->account_hash;
        }
        if ($visitor) {
            $keys[] = $this->hash('user:'.$visitor->id);
        }
        $keys = array_unique($keys);
        sort($keys);
        foreach ($keys as $key) {
            DB::table('share_identities')->insertOrIgnore(['identity' => $key, 'created_at' => now(), 'updated_at' => now()]);
        }
        $identities = DB::table('share_identities')->whereIn('identity', $keys)->orderBy('identity')->lockForUpdate()->get();
        $ids = $identities->pluck('share_referral_id')->filter()->unique()->all();
        $existing = ShareReferral::whereIn('id', $ids)->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
        if ($existing->isNotEmpty()) {
            $winner = $existing->first();
            foreach ($existing->skip(1) as $duplicate) {
                $duplicate->update(['status' => 'merged']);
                DB::table('share_identities')->where('share_referral_id', $duplicate->id)->update(['share_referral_id' => $winner->id]);
                ShareEvent::create(['share_referral_id' => $duplicate->id, 'event' => 'merged', 'reason' => '登录后关联访客身份，保留首次归因']);
            }
            $status = 'duplicate';
        } else {
            $winner = ShareReferral::create(['share_link_id' => $visit->share_link_id, 'user_id' => $visit->link->user_id]);
            $status = 'valid';
        }
        DB::table('share_identities')->whereIn('identity', $keys)->update(['share_referral_id' => $winner->id, 'updated_at' => now()]);
        $visit->update(['status' => $status, 'reason' => $status === 'valid' ? '首次有效访问' : '已归因访客', 'account_hash' => $visitor ? $this->hash('user:'.$visitor->id) : $visit->account_hash]);
        if ($status === 'valid' && $visit->link->user) {
            app(BadgeService::class)->evaluate($visit->link->user);
        }
    }

    public function mergeLogin(Request $request): void
    {
        $hash = $request->session()->get('share_visitor_hash');
        if (! $hash && $request->cookie('share_visitor')) {
            $hash = $this->hash('visitor:'.$request->cookie('share_visitor'));
            $request->session()->put('share_visitor_hash', $hash);
        }
        if (! $hash || ! $request->user()) {
            return;
        }
        $account = $this->hash('user:'.$request->user()->id);
        DB::table('share_known_users')->insertOrIgnore(['identity' => $hash, 'user_id' => $request->user()->id]);
        // 登录后的身份合并只处理已有有效归因，不产生新奖励。
        DB::transaction(function () use ($hash, $account, $request) {
            foreach ([$hash, $account] as $key) {
                DB::table('share_identities')->insertOrIgnore(['identity' => $key, 'created_at' => now(), 'updated_at' => now()]);
            }
            $rows = DB::table('share_identities')->whereIn('identity', [$hash, $account])->orderBy('identity')->lockForUpdate()->get();
            $refs = ShareReferral::whereIn('id', $rows->pluck('share_referral_id')->filter())->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
            if ($refs->isEmpty()) {
                return;
            }
            $winner = $refs->first();
            if ($winner->user_id === $request->user()->id && $winner->status === 'valid') {
                $winner->update(['status' => 'revoked']);
                ShareEvent::create(['share_referral_id' => $winner->id, 'event' => 'self_excluded', 'reason' => '登录后识别为分享者本人']);
            }
            foreach ($refs->skip(1) as $ref) {
                $ref->update(['status' => 'merged']);
                DB::table('share_identities')->where('share_referral_id', $ref->id)->update(['share_referral_id' => $winner->id]);
                ShareEvent::create(['share_referral_id' => $ref->id, 'event' => 'merged', 'reason' => '登录合并，去除跨身份重复归因']);
            }
            DB::table('share_identities')->whereIn('identity', [$hash, $account])->update(['share_referral_id' => $winner->id, 'updated_at' => now()]);
        }, 3);
    }

    public function moderate(User $actor, string $kind, int $id, bool $enabled, string $reason): void
    {
        abort_unless($actor->isAdministrator(), 403);
        abort_if(trim($reason) === '', 422);
        DB::transaction(function () use ($actor, $kind, $id, $enabled, $reason) {
            if ($kind === 'link') {
                ShareLink::whereKey($id)->lockForUpdate()->firstOrFail()->update(['is_active' => $enabled]);
            } else {
                $ref = ShareReferral::whereKey($id)->lockForUpdate()->firstOrFail();
                abort_if($ref->status === 'merged', 422);
                $ref->update(['status' => $enabled ? 'valid' : 'revoked']);
            }
            ShareEvent::create(['actor_id' => $actor->id, $kind === 'link' ? 'share_link_id' : 'share_referral_id' => $id, 'event' => $enabled ? 'restored' : 'revoked', 'reason' => $reason]);
        });
        if ($kind === 'referral' && $enabled) {
            $user = User::find(ShareReferral::findOrFail($id)->user_id);
            if ($user) {
                app(BadgeService::class)->evaluate($user);
            }
        }
    }
}
