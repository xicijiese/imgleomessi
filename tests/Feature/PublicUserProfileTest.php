<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\SponsorshipOrder;
use App\Models\SponsorshipPlan;
use App\Models\SupporterProfile;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicUserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_is_private_by_default_and_has_no_public_index(): void
    {
        $user = User::factory()->create(['name' => '默认私密用户']);

        $this->get('/users/'.$user->id)->assertNotFound();
        $this->get('/users')->assertNotFound();
    }

    public function test_guest_can_visit_enabled_profile_without_private_fields(): void
    {
        $user = User::factory()->create([
            'name' => '公开整理者',
            'email' => 'private@example.test',
            'profile_public' => true,
            'profile_bio' => '只公开展示资料整理相关信息。',
            'moderation_note' => '后台用户备注',
        ]);
        $photo = $this->publicPhoto('公开评论目标');
        $hiddenPhoto = $this->publicPhoto('受限图片标题', ['copyright_status' => 'restricted']);
        $badge = Badge::query()->create([
            'name' => '资料整理者',
            'slug' => 'profile-curator',
            'description' => '参与资料整理',
            'icon_key' => '星',
            'color' => '#3da9fc',
            'rule_type' => 'manual',
            'is_active' => true,
        ]);
        UserBadge::query()->create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'status' => 'earned',
            'equipped' => true,
            'source' => 'manual',
            'note' => '后台勋章备注',
            'awarded_at' => now(),
        ]);
        SupporterProfile::query()->create([
            'user_id' => $user->id,
            'display_name' => '公开整理者',
            'show_publicly' => true,
            'total_amount_cents' => 9900,
            'badge_level' => 'gold',
            'last_supported_at' => now(),
        ]);
        $plan = SponsorshipPlan::query()->create([
            'name' => '年度支持',
            'slug' => 'annual-support',
            'amount_cents' => 9900,
            'duration_days' => 365,
            'badge_level' => 'gold',
            'benefits' => '测试权益',
            'internal_note' => '方案内部备注',
        ]);
        SponsorshipOrder::query()->create([
            'order_no' => 'ORDER-PRIVATE-001',
            'user_id' => $user->id,
            'sponsorship_plan_id' => $plan->id,
            'amount_cents' => 9900,
            'status' => 'paid',
            'transaction_id' => 'TX-PRIVATE-001',
            'admin_note' => '订单后台备注',
            'paid_at' => now(),
        ]);

        Comment::query()->create(['photo_id' => $photo->id, 'user_id' => $user->id, 'type' => 'discussion', 'content' => '这条普通评论可以公开', 'status' => 'published']);
        Comment::query()->create(['photo_id' => $photo->id, 'user_id' => $user->id, 'type' => 'discussion', 'content' => '待审核评论不公开', 'status' => 'pending']);
        Comment::query()->create(['photo_id' => $photo->id, 'user_id' => $user->id, 'type' => 'correction', 'content' => '纠错投稿不公开', 'status' => 'published']);
        Comment::query()->create(['photo_id' => $hiddenPhoto->id, 'user_id' => $user->id, 'type' => 'discussion', 'content' => '受限图片评论不公开', 'status' => 'published']);

        $this->get('/users/'.$user->id)
            ->assertOk()
            ->assertDontSee('private@example.test')
            ->assertDontSee('ORDER-PRIVATE-001')
            ->assertDontSee('TX-PRIVATE-001')
            ->assertDontSee('订单后台备注')
            ->assertDontSee('后台用户备注')
            ->assertDontSee('后台勋章备注')
            ->assertDontSee('待审核评论不公开')
            ->assertDontSee('纠错投稿不公开')
            ->assertDontSee('受限图片评论不公开')
            ->assertDontSee('受限图片标题')
            ->assertDontSee('¥99.00')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Show')
                ->where('profilePage.profile.user.name', '公开整理者')
                ->where('profilePage.profile.user.bio', '只公开展示资料整理相关信息。')
                ->where('profilePage.profile.summary.badges_count', 1)
                ->where('profilePage.profile.summary.public_comments_count', 1)
                ->where('profilePage.profile.summary.is_public_supporter', true)
                ->where('profilePage.profile.supporter.badge_label', '金色守护者')
                ->where('profilePage.profile.equipped_badge.name', '资料整理者')
                ->where('profilePage.profile.comments.0.content', '这条普通评论可以公开')
                ->where('profilePage.profile.comments.0.photo.title', '公开评论目标')
            );
    }

    public function test_user_can_open_and_close_public_profile_from_user_center(): void
    {
        $user = User::factory()->create(['profile_public' => false]);

        $this->actingAs($user)
            ->patch('/me/public-profile', [
                'profile_public' => true,
                'profile_bio' => '公开简介',
            ])
            ->assertRedirect('/me');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'profile_public' => true,
            'profile_bio' => '公开简介',
        ]);

        $this->actingAs($user)
            ->get('/me')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Show')
                ->where('me.user.profile_public', true)
                ->where('me.user.profile_bio', '公开简介')
                ->where('me.user.public_profile_url', '/users/'.$user->id)
            );

        $this->get('/users/'.$user->id)->assertOk();

        $this->actingAs($user)
            ->patch('/me/public-profile', [
                'profile_public' => false,
                'profile_bio' => '',
            ])
            ->assertRedirect('/me');

        $this->get('/users/'.$user->id)->assertNotFound();
    }

    public function test_banned_user_public_profile_is_not_accessible(): void
    {
        $user = User::factory()->create([
            'profile_public' => true,
            'status' => 'banned',
            'ban_reason' => '测试封禁',
        ]);

        $this->get('/users/'.$user->id)->assertNotFound();
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
            'copyright_status' => 'credited',
        ], $attributes));
    }
}
