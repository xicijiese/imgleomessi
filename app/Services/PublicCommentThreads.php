<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicCommentThreads
{
    public function visible($query)
    {
        return $query->published()->discussion()->whereHas('photo', fn ($q) => $q->published()->whereNotIn('copyright_status', ['restricted', 'remove_requested']))->where(fn ($q) => $q->whereNull('parent_id')->orWhereHas('parent', fn ($p) => $p->published()->discussion()->whereNull('parent_id')));
    }

    private function query(Request $request, $base = null)
    {
        return ($base ?? Comment::query())->with(['user.supporterProfile', 'user.contributorProfile', 'user.userBadges' => fn ($q) => $q->where('status', 'earned')->where('equipped', true)->with('badge')])->select('comments.*')->selectSub(DB::table('comment_likes')->selectRaw('count(*)')->whereColumn('comment_id', 'comments.id')->where('user_id', $request->user()?->id ?? 0), 'viewer_liked');
    }

    public function page(Photo $photo, Request $request, ?Comment $root = null): array
    {
        $q = $this->visible($this->query($request))->where('photo_id', $photo->id);
        $root ? $q->where('parent_id', $root->id) : $q->whereNull('parent_id');
        $root ? $q->oldest('created_at')->oldest('id') : $q->latest('created_at')->latest('id');
        $p = $q->paginate($root ? 10 : 20, ['*'], $root ? 'reply_page' : 'comment_page');
        $items = $p->getCollection();
        $replies = collect();
        if (! $root && $items->isNotEmpty()) {
            $items->loadCount(['replies' => fn ($q) => $q->published()->discussion()]);
            $items->load(['replies' => fn ($q) => $this->visible($this->query($request, $q))->latest('created_at')->latest('id')->limit(3)]);
            $replies = $items->flatMap(fn ($c) => $c->replies);
        }
        // 仅批量读取展示身份依据，不在每条评论中重复查询赞助账号。
        $users = $items->concat($replies)->pluck('user')->filter()->unique('id');
        $accounts = DB::table('sponsorship_accounts')->whereIn('user_id', $users->pluck('id'))->get()->keyBy('user_id');
        $replyTargets = $this->visible(Comment::query())
            ->where('photo_id', $photo->id)
            ->whereIn('id', $items->concat($replies)->map(fn ($c) => $c->meta['reply_to_id'] ?? null)->filter())
            ->with('user:id,name')->get()->keyBy('id');
        $serialize = function (Comment $c) use ($accounts, $replyTargets) {
            $u = $c->user;
            $active = $u && ! $u->isBanned() && $u->status === 'active';
            $supporter = $active && $u->supporterProfile?->show_publicly && ($u->supporter_until?->isFuture() || (bool) ($accounts->get($u->id)?->legacy_permanent ?? false) || (! $accounts->has($u->id) && $u->supporter_until === null));

            return ['id' => $c->id, 'user_name' => $u?->name ?? '用户', 'content' => $c->content, 'created_at' => $c->created_at?->format('Y-m-d H:i'), 'liked' => (bool) $c->viewer_liked, 'like_count' => $c->like_count, 'reply_to' => $replyTargets->get($c->meta['reply_to_id'] ?? null)?->user?->name, 'author' => ['name' => $u?->name ?? '用户', 'avatar' => $active ? $u->avatar : null, 'url' => $active && $u->profile_public ? '/users/'.$u->id : null, 'identities' => array_values(array_filter([$supporter ? '网站守护者' : null, $active && $u->contributorProfile?->is_public && $u->contributorProfile->revoked_at === null ? '档案共建者' : null])), 'badges' => $active ? $u->userBadges->filter(fn ($b) => $b->badge?->is_active)->map(fn ($b) => ['id' => $b->badge->id, 'name' => $b->badge->name, 'icon_key' => $b->badge->icon_key, 'color' => $b->badge->color, 'image_url' => $b->badge->image_url])->values()->all() : []]];
        };

        return ['data' => $items->map(function ($c) use ($root, $replies, $serialize) {
            $item = $serialize($c);
            if (! $root) {
                $children = $replies->where('parent_id', $c->id)->sortBy([['created_at', 'asc'], ['id', 'asc']]);
                $item['replies'] = $children->take(3)->map($serialize)->values()->all();
                $item['reply_count'] = $c->replies_count;
            }

            return $item;
        })->all(), 'page' => $p->currentPage(), 'has_more' => $p->hasMorePages(), 'total' => $p->total()];
    }
}
