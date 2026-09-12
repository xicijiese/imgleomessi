<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminAuditLogs\AdminAuditLogResource;
use App\Filament\Resources\UserLoginHistories\UserLoginHistoryResource;
use App\Models\User;
use App\Services\AdminUserManagementService;
use App\Services\UserAvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_remove_a_public_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'profile_public' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('portrait.png', 256, 256),
            ])
            ->assertRedirect(route('profile.edit'));

        $user = $user->fresh();
        $this->assertNotNull($user->avatar_url);
        Storage::disk('public')->assertExists($user->avatar_url);
        $this->assertSame('/storage/'.$user->avatar_url, $user->avatar);
        $this->assertArrayNotHasKey('avatar_url', $user->toArray());
        $this->assertSame($user->avatar, $user->toArray()['avatar']);

        $oldPath = $user->avatar_url;
        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'remove_avatar' => true,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->fresh()->avatar_url);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_admin_user_management_records_role_status_and_session_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $target = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $service = app(AdminUserManagementService::class);

        $service->changeRole($target, $admin, 'editor');
        $service->disable($target->fresh(), $admin);
        $service->enable($target->fresh(), $admin);
        $service->revokeSessions($target->fresh(), $admin);

        $target = $target->fresh();
        $this->assertSame('editor', $target->role);
        $this->assertSame('active', $target->status);
        $this->assertSame(2, $target->session_version);
        $this->assertDatabaseCount('admin_audit_logs', 4);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'action' => 'user.role_changed',
        ]);
    }

    public function test_role_change_is_blocked_while_contributor_profile_is_active(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $target = User::factory()->create(['role' => 'editor', 'status' => 'active']);
        $target->contributorProfile()->create([
            'display_name' => '档案共建者',
            'is_public' => false,
            'granted_by' => $admin->id,
            'granted_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(AdminUserManagementService::class)->changeRole($target, $admin, 'user');
    }

    public function test_last_active_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'disabled']);
        $operator = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->expectException(ValidationException::class);
        app(AdminUserManagementService::class)->changeRole($admin, $operator, 'editor');
    }

    public function test_admin_can_open_login_history_and_audit_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->get(AdminAuditLogResource::getUrl())
            ->assertOk();
        $this->actingAs($admin)
            ->get(UserLoginHistoryResource::getUrl())
            ->assertOk();
    }

    public function test_avatar_service_removes_an_existing_avatar_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $service = app(UserAvatarService::class);
        $service->replace($user, UploadedFile::fake()->image('first.jpg'));
        $oldPath = $user->fresh()->avatar_url;

        $service->remove($user->fresh());

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertNull($user->fresh()->avatar_url);
    }
}