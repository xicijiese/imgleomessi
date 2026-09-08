<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\PhotoFavorite;
use App\Models\Report;
use App\Models\User;
use App\Notifications\UserCenterNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_user_center_pages(): void
    {
        foreach (['/me', '/me/favorites', '/me/comments', '/me/reports', '/me/notifications', '/me/sponsorships', '/me/badges'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_user_center_overview_only_uses_current_user_data(): void
    {
        $user = User::factory()->create(['name' => '当前用户']);
        $other = User::factory()->create(['name' => '其他用户']);
        $photo = $this->publicPhoto('我的收藏图片');
        $otherPhoto = $this->publicPhoto('其他人的图片');

        PhotoFavorite::query()->create(['photo_id' => $photo->id, 'user_id' => $user->id]);
        PhotoFavorite::query()->create(['photo_id' => $otherPhoto->id, 'user_id' => $other->id]);
        Comment::query()->create(['photo_id' => $photo->id, 'user_id' => $user->id, 'type' => 'discussion', 'content' => '我的评论', 'status' => 'pending']);
        Comment::query()->create(['photo_id' => $otherPhoto->id, 'user_id' => $other->id, 'type' => 'discussion', 'content' => '其他人的评论', 'status' => 'pending']);
        $user->notify(new UserCenterNotification('system', '我的通知', '只属于当前用户'));
        $other->notify(new UserCenterNotification('system', '其他通知', '不应该出现'));

        $this->actingAs($user)
            ->get('/me')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Show')
                ->where('me.user.name', '当前用户')
                ->where('me.summary.favorites_count', 1)
                ->where('me.summary.comments_count', 1)
                ->where('me.summary.unread_notifications_count', 1)
                ->where('me.recent_favorites.0.photo.title', '我的收藏图片')
                ->where('me.recent_comments.0.content', '我的评论')
                ->where('me.recent_notifications.0.title', '我的通知')
            );
    }

    public function test_favorites_page_hides_non_public_photo_and_allows_owner_to_remove(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $public = $this->publicPhoto('公开收藏图片');
        $draft = $this->publicPhoto('草稿收藏图片', ['status' => 'draft']);
        $favorite = PhotoFavorite::query()->create(['photo_id' => $public->id, 'user_id' => $user->id]);
        PhotoFavorite::query()->create(['photo_id' => $draft->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/me/favorites')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Favorites')
                ->has('me.favorites.data', 2)
                ->where('me.favorites.data.0.photo.is_available', false)
                ->where('me.favorites.data.0.photo.title', '内容暂不可见')
            );

        $this->actingAs($other)
            ->deleteJson('/me/favorites/'.$favorite->id)
            ->assertNotFound();

        $this->actingAs($user)
            ->deleteJson('/me/favorites/'.$favorite->id)
            ->assertOk()
            ->assertJson(['removed' => true]);

        $this->assertDatabaseMissing('photo_favorites', ['id' => $favorite->id]);
    }

    public function test_comments_page_exposes_safe_status_without_internal_moderation_fields(): void
    {
        $user = User::factory()->create();
        $photo = $this->publicPhoto('评论目标图片');
        Comment::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $user->id,
            'type' => 'correction',
            'content' => '建议补充来源',
            'status' => 'rejected',
            'correction_field' => 'source',
            'suggested_value' => '官网图集',
            'moderation_note' => '后台秘密备注',
            'sensitive_word_hits' => [['word' => '内部词', 'severity' => 'high']],
        ]);

        $this->actingAs($user)
            ->get('/me/comments')
            ->assertOk()
            ->assertDontSee('后台秘密备注')
            ->assertDontSee('内部词')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Comments')
                ->where('me.comments.data.0.type_label', '图片信息补充/纠错')
                ->where('me.comments.data.0.status_label', '已拒绝')
                ->where('me.comments.data.0.result_message', '内容未通过审核。')
            );
    }

    public function test_reports_page_exposes_safe_result_without_internal_note(): void
    {
        $user = User::factory()->create();
        $photo = $this->publicPhoto('举报目标图片');
        $comment = Comment::query()->create(['photo_id' => $photo->id, 'user_id' => User::factory()->create()->id, 'type' => 'discussion', 'content' => '被举报评论', 'status' => 'published']);
        Report::query()->create([
            'user_id' => $user->id,
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'reason' => 'abuse',
            'details' => '我看到攻击内容',
            'status' => 'resolved',
            'internal_note' => '后台处理秘密',
        ]);

        $this->actingAs($user)
            ->get('/me/reports')
            ->assertOk()
            ->assertDontSee('后台处理秘密')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Reports')
                ->where('me.reports.data.0.reason_label', '攻击辱骂')
                ->where('me.reports.data.0.status_label', '已处理')
                ->where('me.reports.data.0.result_message', '举报已处理，感谢帮助维护社区秩序。')
            );
    }

    public function test_review_report_and_ban_actions_create_user_notifications(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $photo = $this->publicPhoto('通知目标图片');
        $comment = Comment::query()->create(['photo_id' => $photo->id, 'user_id' => $user->id, 'type' => 'discussion', 'content' => '待审核评论', 'status' => 'pending']);
        $report = Report::query()->create(['user_id' => $user->id, 'target_type' => 'comment', 'target_id' => $comment->id, 'reason' => 'spam']);

        $comment->review('published', $admin, '后台审核备注');
        $report->reject($admin, '后台举报备注');
        $user->ban('频繁违规', '后台封禁备注');

        $this->assertSame(3, $user->notifications()->count());
        $this->actingAs($user)
            ->get('/me/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Notifications')
                ->where('me.summary.unread_notifications_count', 3)
                ->has('me.notifications.data', 3)
            );
    }

    public function test_user_can_mark_notifications_read_but_not_other_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->notify(new UserCenterNotification('system', '第一条通知', '内容'));
        $user->notify(new UserCenterNotification('system', '第二条通知', '内容'));
        $other->notify(new UserCenterNotification('system', '别人的通知', '内容'));

        $ownNotification = $user->notifications()->oldest()->firstOrFail();
        $otherNotification = $other->notifications()->firstOrFail();

        $this->actingAs($user)
            ->patchJson('/me/notifications/'.$otherNotification->id.'/read')
            ->assertNotFound();

        $this->actingAs($user)
            ->patchJson('/me/notifications/'.$ownNotification->id.'/read')
            ->assertOk()
            ->assertJson(['read' => true]);

        $this->assertNotNull($ownNotification->fresh()->read_at);

        $this->actingAs($user)
            ->patchJson('/me/notifications/read-all')
            ->assertOk()
            ->assertJson(['read' => true]);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publicPhoto(string $title, array $attributes = []): Photo
    {
        return Photo::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'status' => 'published',
            'published_at' => now(),
        ], $attributes));
    }

    private function category(string $rootName = '赛事', string $childName = '世界杯'): Category
    {
        $root = Category::query()->create([
            'name' => $rootName,
            'slug' => Str::slug($rootName).'-'.Str::random(6),
            'visibility' => 'public',
            'is_system' => true,
        ]);

        return Category::query()->create([
            'parent_id' => $root->id,
            'name' => $childName,
            'slug' => Str::slug($childName).'-'.Str::random(6),
            'visibility' => 'public',
            'is_system' => false,
        ]);
    }
}
