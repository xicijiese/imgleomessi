<?php

namespace Tests\Feature;

use App\Filament\Widgets\GalleryOverview;
use App\Models\AdminAuditLog;
use App\Models\Album;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\ProcessingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_dashboard_application_reminder_bypasses_cached_counts(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        Livewire::test(GalleryOverview::class)->assertDontSee('条投稿资格申请待审核');
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        app(\App\Services\SubmissionQualification::class)->apply($user, '我有大量高清梅西图片资源愿意投稿');
        Livewire::test(GalleryOverview::class)->assertSee('有 1 条投稿资格申请待审核');
        $this->assertSame('1', \App\Filament\Resources\SubmissionApplications\SubmissionApplicationResource::getNavigationBadge());
    }

    public function test_dashboard_counts_real_records_and_only_manual_review_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);
        $photo = Photo::create(['title' => '公开档案', 'status' => 'published']);
        Photo::create(['title' => '草稿档案', 'status' => 'draft']);
        Album::create(['title' => '历史相册', 'slug' => 'history']);
        foreach (['discussion', 'correction'] as $type) {
            Comment::create(['user_id' => $admin->id, 'photo_id' => $photo->id, 'type' => $type, 'content' => '待处理内容', 'status' => 'pending']);
        }
        ProcessingJob::create(['photo_id' => $photo->id, 'type' => 'metadata', 'status' => 'failed']);
        ProcessingJob::create(['photo_id' => $photo->id, 'type' => 'labels', 'status' => 'failed']);

        Livewire::test(GalleryOverview::class)
            ->assertViewHas('snapshot', fn (array $data): bool => $data['photos'] === 2
                && $data['published'] === 1 && $data['albums'] === 1
                && $data['users'] === 1 && $data['corrections'] === 1
                && (int) $data['processing']['failed'] === 1)
            ->assertSee('暂无上传任务')
            ->assertSee('暂无管理员操作记录')
            ->assertViewHas('todos', function (array $items): bool {
                $corrections = collect($items)->firstWhere('label', '待审纠错');
                parse_str(parse_url($corrections['url'], PHP_URL_QUERY), $query);

                return $query['filters'] === ['status' => ['value' => 'pending'], 'type' => ['value' => 'correction']];
            });

        $this->get('/admin')->assertOk()->assertSee('图库概览')->assertSee('入库处理');
    }

    public function test_editor_does_not_receive_administrator_data_even_after_cache_is_warm(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => '仅管理员可见的操作者']);
        AdminAuditLog::create(['actor_user_id' => $admin->id, 'action' => 'private.audit']);
        $this->actingAs($admin);
        Livewire::test(GalleryOverview::class)->assertSee('private.audit');

        $editor = User::factory()->create(['role' => 'editor', 'status' => 'active']);
        $this->actingAs($editor);
        Livewire::test(GalleryOverview::class)
            ->assertSee('待审投稿')
            ->assertDontSee('用户总数')
            ->assertDontSee('入库处理')
            ->assertDontSee('待审纠错')
            ->assertDontSee('private.audit')
            ->assertDontSee('仅管理员可见的操作者')
            ->assertViewHas('snapshot', fn (array $data): bool => array_intersect_key($data, array_flip(['users', 'audits', 'batches', 'processing'])) === []);
    }

    public function test_snapshot_refreshes_after_one_minute(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        Livewire::test(GalleryOverview::class)->assertViewHas('snapshot', fn (array $data): bool => $data['photos'] === 0);
        Photo::create(['title' => '新增档案']);
        Livewire::test(GalleryOverview::class)->assertViewHas('snapshot', fn (array $data): bool => $data['photos'] === 0);
        $this->travel(61)->seconds();
        Livewire::test(GalleryOverview::class)->assertViewHas('snapshot', fn (array $data): bool => $data['photos'] === 1);
    }

    public function test_ordinary_and_disabled_users_cannot_request_statistics(): void
    {
        foreach ([['user', 'active'], ['admin', 'disabled']] as [$role, $status]) {
            $this->actingAs(User::factory()->create(compact('role', 'status')));
            Livewire::test(GalleryOverview::class)->assertForbidden();
            $this->get('/admin')->assertForbidden();
        }
    }
}
