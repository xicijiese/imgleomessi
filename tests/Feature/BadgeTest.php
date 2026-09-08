<?php

namespace Tests\Feature;

use App\Filament\Resources\Badges\BadgeResource;
use App\Models\Badge;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\SponsorshipPlan;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\SponsorshipService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_badges_page_awards_registered_badge_and_can_equip_badge(): void
    {
        $this->seed(BadgeSeeder::class);
        $user = User::factory()->create();
        $manualBadge = Badge::query()->where('slug', 'curator-pick')->firstOrFail();

        $this->actingAs($user)
            ->get('/me/badges')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Badges')
                ->where('me.user.email', $user->email)
                ->has('me.badges', 5)
                ->where('me.equipped_badge.slug', 'archive-visitor')
            );

        app(BadgeService::class)->award($user->refresh(), $manualBadge, 'manual');

        $this->actingAs($user)
            ->patch('/me/badges/'.$manualBadge->id.'/equip')
            ->assertRedirect();

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $manualBadge->id,
            'status' => 'earned',
            'equipped' => true,
        ]);
    }

    public function test_favorite_action_awards_favorite_badge(): void
    {
        $this->seed(BadgeSeeder::class);
        $user = User::factory()->create();
        $photo = $this->publicPhoto('收藏触发目标');
        $badge = Badge::query()->where('slug', 'first-favorite')->firstOrFail();

        $this->actingAs($user)
            ->postJson('/photos/'.$photo->uuid.'/favorite')
            ->assertOk()
            ->assertJson(['is_favorited' => true]);

        $this->assertDatabaseHas('photo_favorites', [
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'status' => 'earned',
        ]);
    }

    public function test_published_discussion_comment_awards_comment_badge(): void
    {
        $this->seed(BadgeSeeder::class);
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $photo = $this->publicPhoto('评论触发目标');
        $badge = Badge::query()->where('slug', 'discussion-starter')->firstOrFail();
        $comment = Comment::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $user->id,
            'type' => 'discussion',
            'content' => '等待审核的普通评论',
            'status' => 'pending',
        ]);

        $comment->review('published', $admin, '通过');

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'status' => 'earned',
        ]);
    }

    public function test_paid_sponsorship_awards_supporter_badge(): void
    {
        $this->seed(BadgeSeeder::class);
        $user = User::factory()->create();
        $plan = SponsorshipPlan::query()->create([
            'name' => '测试支持方案',
            'slug' => 'test-support-plan',
            'amount_cents' => 990,
            'duration_days' => 30,
            'badge_level' => 'supporter',
            'is_active' => true,
        ]);
        $badge = Badge::query()->where('slug', 'site-supporter')->firstOrFail();
        $service = app(SponsorshipService::class);
        $order = $service->createOrder($user, $plan);

        $service->markPaid($order);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'status' => 'earned',
        ]);
    }

    public function test_admin_can_open_badge_management_and_service_can_revoke_manual_grant(): void
    {
        $this->seed(BadgeSeeder::class);
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $badge = Badge::query()->where('slug', 'curator-pick')->firstOrFail();
        $userBadge = app(BadgeService::class)->award($user, $badge, 'manual', $admin, '人工发放');

        $this->actingAs($admin)
            ->get(BadgeResource::getUrl())
            ->assertOk();

        app(BadgeService::class)->revoke($userBadge, $admin, '撤销测试');

        $this->assertDatabaseHas('user_badges', [
            'id' => $userBadge->id,
            'status' => 'revoked',
            'equipped' => false,
        ]);
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
            'copyright_status' => 'credited',
            'published_at' => now(),
        ], $attributes));
    }
}
