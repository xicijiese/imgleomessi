<?php

namespace Tests\Feature;

use App\Filament\Pages\InteractionStats;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\PhotoFavorite;
use App\Models\PhotoLike;
use App\Models\PhotoShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_rankings_orders_by_confirmed_hot_score_and_excludes_non_public_records(): void
    {
        $alpha = $this->publicPhoto('综合热度第一');
        $beta = $this->publicPhoto('点赞更多但热度第二');
        $draft = $this->publicPhoto('草稿不应上榜', ['status' => 'draft']);
        $restricted = $this->publicPhoto('受限不应上榜', ['copyright_status' => 'restricted']);
        $users = User::factory()->count(4)->create();

        $this->favorite($alpha, $users[0]);
        $this->like($alpha, $users[0]);
        $this->discussion($alpha, $users[1], 'published');
        $this->discussion($alpha, $users[2], 'pending');
        $this->correction($alpha, $users[3]);
        $this->share($alpha, $users[0]);

        $this->like($beta, $users[0]);
        $this->like($beta, $users[1]);
        $this->like($beta, $users[2]);

        $this->favorite($draft, $users[0]);
        $this->like($restricted, $users[0]);

        $this->get('/rankings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rankings/Index')
                ->where('rankings.filters.type', 'hot')
                ->where('rankings.filters.window', 'all')
                ->where('rankings.summary.formula', '收藏 x4 + 点赞 x3 + 评论 x2 + 分享 x1')
                ->has('rankings.items', 2)
                ->where('rankings.items.0.title', '综合热度第一')
                ->where('rankings.items.0.metrics.hot_score', 10)
                ->where('rankings.items.0.metrics.likes', 1)
                ->where('rankings.items.0.metrics.favorites', 1)
                ->where('rankings.items.0.metrics.comments', 1)
                ->where('rankings.items.0.metrics.shares', 1)
                ->where('rankings.items.1.title', '点赞更多但热度第二')
                ->where('rankings.items.1.metrics.hot_score', 9)
            );
    }

    public function test_public_rankings_switches_type_and_time_window(): void
    {
        $recent = $this->publicPhoto('近 7 天上榜图片');
        $old = $this->publicPhoto('旧互动不进近 30 天');
        $users = User::factory()->count(3)->create();

        $this->like($recent, $users[0], now()->subDays(2));
        $this->like($recent, $users[1], now()->subDays(2));
        $this->favorite($old, $users[0], now()->subDays(45));
        $this->favorite($old, $users[1], now()->subDays(45));
        $this->favorite($old, $users[2], now()->subDays(45));

        $this->get('/rankings?type=likes&window=7d')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rankings/Index')
                ->where('rankings.filters.type', 'likes')
                ->where('rankings.filters.window', '7d')
                ->has('rankings.items', 1)
                ->where('rankings.items.0.title', '近 7 天上榜图片')
                ->where('rankings.items.0.metrics.likes', 2)
            );

        $this->get('/rankings?type=favorites&window=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rankings.filters.type', 'favorites')
                ->where('rankings.filters.window', 'all')
                ->where('rankings.items.0.title', '旧互动不进近 30 天')
                ->where('rankings.items.0.metrics.favorites', 3)
            );
    }

    public function test_rankings_navigation_is_enabled_by_default(): void
    {
        $this->get('/rankings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rankings.navigation.4.label', '时间线')
                ->where('rankings.navigation.4.url', '/timeline')
                ->where('rankings.navigation.5.label', '排行榜')
                ->where('rankings.navigation.5.url', '/rankings')
            );
    }

    public function test_admin_can_visit_interaction_stats_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']))
            ->get(InteractionStats::getUrl())
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publicPhoto(string $title, array $attributes = []): Photo
    {
        return Photo::query()->create(array_merge([
            'title' => $title,
            'status' => 'published',
            'copyright_status' => 'credited',
            'published_at' => now(),
        ], $attributes));
    }

    private function like(Photo $photo, User $user, ?Carbon $createdAt = null): void
    {
        $record = PhotoLike::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);

        $this->setCreatedAt($record, $createdAt);
    }

    private function favorite(Photo $photo, User $user, ?Carbon $createdAt = null): void
    {
        $record = PhotoFavorite::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);

        $this->setCreatedAt($record, $createdAt);
    }

    private function share(Photo $photo, User $user, ?Carbon $createdAt = null): void
    {
        $record = PhotoShare::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $user->id,
            'channel' => 'copy_link',
            'page_url' => 'http://127.0.0.1:8000/photos/'.$photo->uuid,
        ]);

        $this->setCreatedAt($record, $createdAt);
    }

    private function discussion(Photo $photo, User $user, string $status, ?Carbon $createdAt = null): void
    {
        $record = Comment::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $user->id,
            'type' => 'discussion',
            'content' => '测试评论内容',
            'status' => $status,
        ]);

        $this->setCreatedAt($record, $createdAt);
    }

    private function correction(Photo $photo, User $user): void
    {
        Comment::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $user->id,
            'type' => 'correction',
            'content' => '测试纠错内容',
            'status' => 'published',
            'correction_field' => 'source',
        ]);
    }

    private function setCreatedAt($record, ?Carbon $createdAt): void
    {
        if ($createdAt === null) {
            return;
        }

        $record->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }
}
