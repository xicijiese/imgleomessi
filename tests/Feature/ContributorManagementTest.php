<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Tags\TagResource;
use App\Filament\Resources\SponsorshipOrders\SponsorshipOrderResource;
use App\Filament\Resources\Photos\PhotoResource;
use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Albums\AlbumResource;
use App\Filament\Pages\SystemSettings;
use App\Filament\Pages\InteractionStats;

use App\Models\ContributorProfile;
use App\Models\User;
use App\Services\ContributorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_grant_a_registered_user_as_an_archive_contributor(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
        ]);

        app(ContributorService::class)->grant($user, $admin, [
            'display_name' => '蓝色档案共建者',
            'bio' => '负责整理比赛图片和来源资料。',
        ]);

        $this->assertDatabaseHas('contributor_profiles', [
            'user_id' => $user->id,
            'display_name' => '蓝色档案共建者',
            'is_public' => 0,
            'granted_by' => $admin->id,
            'revoked_at' => null,
        ]);
        $this->assertSame('editor', $user->fresh()->role);
        $this->assertTrue($user->fresh()->contributorProfile->isActive());
    }

    public function test_admin_can_update_public_contributor_profile_fields(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
        ]);
        $service = app(ContributorService::class);

        $service->grant($user, $admin);
        $service->updateProfile($user->fresh(), $admin, [
            'display_name' => '蓝色档案共建者',
            'bio' => '负责赛事图片整理。',
            'contribution_focus' => '赛事与来源考据',
            'is_public' => true,
            'sort_order' => 12,
        ]);

        $this->assertDatabaseHas('contributor_profiles', [
            'user_id' => $user->id,
            'display_name' => '蓝色档案共建者',
            'contribution_focus' => '赛事与来源考据',
            'is_public' => 1,
            'sort_order' => 12,
        ]);
    }
    public function test_revoking_a_contributor_restores_a_user_promoted_by_the_grant(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
        ]);
        $service = app(ContributorService::class);

        $service->grant($user, $admin);
        $this->assertSame('editor', $user->fresh()->role);

        $this->assertTrue($service->revoke($user->fresh(), $admin));
        $user = $user->fresh();

        $this->assertSame('user', $user->role);
        $this->assertFalse($user->contributorProfile->isActive());
        $this->assertFalse($user->contributorProfile->is_public);
    }

    public function test_revoke_does_not_downgrade_existing_editor_or_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $editor = User::factory()->create([
            'role' => 'editor',
            'status' => 'active',
        ]);
        $existingAdmin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $service = app(ContributorService::class);

        $service->grant($editor, $admin);
        $service->grant($existingAdmin, $admin);
        $service->revoke($editor->fresh(), $admin);
        $service->revoke($existingAdmin->fresh(), $admin);

        $this->assertSame('editor', $editor->fresh()->role);
        $this->assertSame('admin', $existingAdmin->fresh()->role);
    }

    public function test_non_admin_cannot_grant_or_revoke_archive_contributors(): void
    {
        $editor = User::factory()->create([
            'role' => 'editor',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
        ]);
        $service = app(ContributorService::class);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $service->grant($user, $editor);
    }

    public function test_admin_can_open_user_management_with_contributor_controls(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(UserResource::getUrl())
            ->assertOk()
            ->assertSee('用户管理');
    }
    public function test_editor_cannot_access_user_management_by_direct_url(): void
    {
        $editor = User::factory()->create([
            'role' => 'editor',
            'status' => 'active',
        ]);

        $this->actingAs($editor)
            ->get(UserResource::getUrl())
            ->assertForbidden();
    }

    public function test_editor_can_access_gallery_resources_but_not_admin_resources(): void
    {
        $editor = User::factory()->create([
            'role' => 'editor',
            'status' => 'active',
        ]);

        $this->actingAs($editor);

        foreach ([
            PhotoResource::getUrl(),
            AlbumResource::getUrl(),
            CategoryResource::getUrl(),
            TagResource::getUrl(),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        foreach ([
            UserResource::getUrl(),
            CommentResource::getUrl(),
            SponsorshipOrderResource::getUrl(),
            SystemSettings::getUrl(),
            InteractionStats::getUrl(),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}