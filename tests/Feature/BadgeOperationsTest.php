<?php

namespace Tests\Feature;

use App\Filament\Resources\Badges\BadgeResource;
use App\Filament\Resources\Badges\Pages\ManageBadges;
use App\Jobs\ProcessBadgeGrantBatch;
use App\Models\Badge;
use App\Models\BadgeGrantBatch;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeGrantService;
use App\Services\BadgeService;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class BadgeOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function badge(array $attributes = []): Badge
    {
        return Badge::query()->create([...['name' => '纪念勋章', 'slug' => fake()->unique()->slug(), 'rule_type' => 'manual'], ...$attributes]);
    }

    public function test_multiple_badges_can_be_equipped_and_independently_removed(): void
    {
        $user = User::factory()->create(['profile_public' => true]);
        $service = app(BadgeService::class);
        $badges = collect(range(1, 6))->map(fn () => $this->badge());
        foreach ($badges as $badge) {
            $grant = $service->award($user, $badge);
            $this->assertFalse($grant->equipped);
            $this->actingAs($user)->patch('/me/badges/'.$badge->id.'/equip', ['equipped' => true])->assertRedirect();
        }
        $this->assertSame(6, $user->equippedBadges()->count());
        $this->actingAs($user)->patch('/me/badges/'.$badges->first()->id.'/equip', ['equipped' => false])->assertRedirect();
        $this->assertSame(5, $user->equippedBadges()->count());
        $this->get('/users/'.$user->id)->assertInertia(fn (Assert $page) => $page->has('profilePage.profile.equipped_badges', 5));
        $other = User::factory()->create();
        $this->actingAs($other)->patch('/me/badges/'.$badges->first()->id.'/equip')->assertNotFound();
    }

    public function test_revoke_blocks_rule_and_manual_reaward_until_explicit_admin_restore(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $badge = $this->badge(['rule_type' => 'registered']);
        $service = app(BadgeService::class);
        $grant = $service->award($user, $badge);
        $service->equip($user, $badge);
        $service->revoke($grant, $admin, '误发');
        $service->evaluate($user);
        $service->award($user, $badge, 'manual', $admin);
        $this->assertSame('revoked', $grant->fresh()->status);
        $this->actingAs($user)->patch('/me/badges/'.$badge->id.'/equip')->assertNotFound();
        $service->restore($grant, $admin, '复核通过');
        $this->assertSame('earned', $grant->fresh()->status);
        $this->assertFalse($grant->fresh()->equipped);
        $this->assertDatabaseCount('badge_events', 3);
        $this->assertDatabaseHas('badge_events', ['action' => 'restored', 'operator_id' => $admin->id, 'note' => '复核通过']);
        $this->assertSame(2, $user->notifications()->count());
    }

    public function test_anniversary_uses_beijing_dates_and_any_successful_public_visit(): void
    {
        $user = User::factory()->create();
        $badge = $this->badge(['rule_type' => 'anniversary', 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-19']);
        $this->travelTo(Carbon::parse('2026-10-18 15:59:59', 'UTC'));
        $this->actingAs($user)->get('/photos')->assertOk();
        $this->assertDatabaseCount('user_badges', 0);
        $this->travelTo(Carbon::parse('2026-10-18 16:00:00', 'UTC'));
        $this->get('/photos')->assertOk();
        $this->get('/photos')->assertOk();
        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id, 'badge_id' => $badge->id, 'equipped' => false]);
        $this->assertDatabaseCount('badge_events', 1);
        $other = User::factory()->create();
        $this->travelTo(Carbon::parse('2026-10-19 16:00:00', 'UTC'));
        $this->actingAs($other)->get('/photos')->assertOk();
        $this->assertDatabaseMissing('user_badges', ['user_id' => $other->id]);
        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id, 'status' => 'earned']);
        $this->travelBack();
    }

    public function test_hall_belongs_to_user_center_and_excludes_inactive_and_internal_fields(): void
    {
        $this->badge(['image_path' => 'badges/animated.gif', 'internal_note' => '不能公开的备注']);
        $this->badge(['is_active' => false, 'name' => '停用勋章']);
        $this->badge(['rule_type' => 'contributions_count', 'rule_threshold' => 50]);
        $this->badge(['rule_type' => 'referrals_count', 'rule_threshold' => 50]);
        $this->get('/badges')->assertRedirect('/me/badges/hall');
        $this->get('/me/badges/hall')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/me/badges/hall')->assertOk()->assertDontSee('不能公开的备注')->assertDontSee('停用勋章')
            ->assertInertia(fn (Assert $page) => $page->component('Me/BadgeHall')->where('hall.user.email', $user->email)->has('hall.summary')->has('hall.badges', 3)->where('hall.badges.0.image_url', '/storage/badges/animated.gif'));
        $user = User::factory()->create();
        app(BadgeService::class)->evaluate($user);
        $this->assertDatabaseCount('user_badges', 0);
    }

    public function test_inactive_earned_badges_are_retained_and_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $badge = $this->badge();
        app(BadgeService::class)->award($user, $badge);
        $badge->update(['is_active' => false]);
        $this->actingAs($user)->get('/me/badges')->assertInertia(fn (Assert $page) => $page->has('me.badges', 1)->where('me.badges.0.is_earned', true));
        $this->expectException(ValidationException::class);
        $badge->delete();
    }

    public function test_batch_snapshot_skips_revoked_and_existing_and_can_be_safely_reprocessed(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $users = User::factory()->count(3)->create();
        $disabled = User::factory()->create(['status' => 'disabled']);
        $badge = $this->badge();
        $badges = app(BadgeService::class);
        $badges->award($users[0], $badge);
        $badges->revoke($badges->award($users[1], $badge), $admin, '不可重发');
        $batch = app(BadgeGrantService::class)->create($badge, $admin, 'all', [], '全体纪念');
        $late = User::factory()->create();
        $this->assertSame(4, $batch->recipients()->count());
        Queue::assertPushed(ProcessBadgeGrantBatch::class);
        $job = new ProcessBadgeGrantBatch($batch->id);
        $job->handle($badges);
        $job->handle($badges);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame(2, $batch->recipients()->where('status', 'awarded')->count());
        $this->assertSame(2, $batch->recipients()->where('status', 'skipped')->count());
        $this->assertDatabaseMissing('user_badges', ['user_id' => $late->id]);
        $this->assertDatabaseMissing('user_badges', ['user_id' => $disabled->id]);
        $this->assertSame('revoked', $users[1]->userBadges()->first()->status);
        $this->assertSame(0, UserBadge::query()->where('equipped', true)->count());
    }

    public function test_batch_failure_can_retry_only_remaining_users_and_duplicate_submissions_are_blocked(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $badge = $this->badge();
        $service = app(BadgeGrantService::class);
        $batch = $service->create($badge, $admin, 'selected', [$admin->id], '测试');
        try {
            $service->create($badge, $admin, 'all', [], '重复');
            $this->fail('应拦截重复提交');
        } catch (ValidationException) {
            $this->assertDatabaseCount('badge_grant_batches', 1);
        }
        (new ProcessBadgeGrantBatch($batch->id))->failed(new \RuntimeException('模拟故障'));
        $this->assertSame('failed', $batch->fresh()->status);
        $service->retry($batch->fresh(), $admin);
        (new ProcessBadgeGrantBatch($batch->id))->handle(app(BadgeService::class));
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertDatabaseCount('user_badges', 1);
    }

    public function test_admin_tabs_and_badge_operations_are_denied_to_editor_and_normal_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);
        foreach (['badges', 'batches', 'events'] as $tab) {
            Livewire::test(ManageBadges::class)->set('activeTab', $tab)->assertSuccessful();
        }
        $editor = User::factory()->create(['role' => 'editor', 'status' => 'active']);
        $this->actingAs($editor)->get(BadgeResource::getUrl())->assertForbidden();
        $this->actingAs(User::factory()->create())->get(BadgeResource::getUrl())->assertForbidden();
    }

    public function test_admin_can_upload_gif_without_altering_original_and_validate_anniversary_dates(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $gif = hex2bin('47494638396101000100800000000000ffffff21f90401000000002c000000000100010000020244010021f90401000000002c00000000010001000002024401003b');
        Livewire::test(ManageBadges::class)->callAction('create', data: [
            'name' => '动画纪念章', 'slug' => 'animated-anniversary', 'rule_type' => 'anniversary',
            'starts_on' => '2026-10-19', 'ends_on' => '2026-10-20',
            'image_path' => UploadedFile::fake()->createWithContent('medal.gif', $gif),
        ])->assertHasNoActionErrors();
        $badge = Badge::query()->where('slug', 'animated-anniversary')->firstOrFail();
        $this->assertSame($gif, Storage::disk('public')->get($badge->image_path));
        Livewire::test(ManageBadges::class)->callAction('create', data: [
            'name' => '日期错误', 'slug' => 'invalid-dates', 'rule_type' => 'anniversary',
            'starts_on' => '2026-10-20', 'ends_on' => '2026-10-19',
        ])->assertHasActionErrors(['ends_on']);
    }

    public function test_admin_grant_revoke_and_restore_actions_produce_audit_records(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $user = User::factory()->create();
        $badge = $this->badge();
        $this->actingAs($admin);
        Livewire::test(ManageBadges::class)->callAction(TestAction::make('grant')->table($badge), data: [
            'audience' => 'selected', 'user_ids' => [$user->id], 'note' => '纪念发放',
        ])->assertHasNoActionErrors();
        $batch = BadgeGrantBatch::query()->firstOrFail();
        (new ProcessBadgeGrantBatch($batch->id))->handle(app(BadgeService::class));
        $grant = $user->userBadges()->firstOrFail();
        Livewire::test(ManageBadges::class)->assertActionHidden(TestAction::make('delete')->table($badge));
        Livewire::test(ManageBadges::class)->callAction(TestAction::make('revoke')->table($badge), data: [
            'user_badge_id' => $grant->id, 'note' => '误发处理',
        ])->assertHasNoActionErrors();
        $this->assertSame('revoked', $grant->fresh()->status);
        Livewire::test(ManageBadges::class)->callAction(TestAction::make('restore')->table($badge), data: [
            'user_badge_id' => $grant->id, 'note' => '复核恢复',
        ])->assertHasNoActionErrors();
        $this->assertSame('earned', $grant->fresh()->status);
        $this->assertDatabaseCount('badge_events', 3);
    }

    public function test_real_database_worker_processes_multiple_chunks_without_duplicate_awards(): void
    {
        config(['queue.default' => 'database']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        User::factory()->count(101)->create(['status' => 'active']);
        $badge = $this->badge();
        $batch = app(BadgeGrantService::class)->create($badge, $admin, 'all', [], '队列验收');
        $this->assertSame('pending', $batch->fresh()->status);
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1, '--timeout' => 120])->assertExitCode(0);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame(102, $batch->recipients()->where('status', 'awarded')->count());
        $this->assertDatabaseCount('badge_events', 102);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_worker_will_not_grant_when_operator_loses_admin_permissions(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $batch = app(BadgeGrantService::class)->create($this->badge(), $admin, 'all', [], '权限校验');
        $admin->update(['role' => 'user']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('提交人的管理员权限已失效');
        (new ProcessBadgeGrantBatch($batch->id))->handle(app(BadgeService::class));
    }

    public function test_disabled_and_banned_accounts_do_not_earn_anniversary_badges(): void
    {
        $this->badge(['rule_type' => 'anniversary', 'starts_on' => now('Asia/Shanghai')->toDateString(), 'ends_on' => now('Asia/Shanghai')->toDateString()]);
        foreach (['disabled', 'banned'] as $status) {
            $user = User::factory()->create(['status' => $status]);
            $this->actingAs($user)->get('/photos')->assertOk();
            $this->actingAs($user)->patch('/me/badges/1/equip')->assertForbidden();
        }
        $this->assertDatabaseCount('user_badges', 0);
    }
}
