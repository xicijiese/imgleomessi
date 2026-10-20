<?php

namespace Tests\Feature;

use App\Filament\Resources\PhotoSubmissions\Pages\ManagePhotoSubmissions;
use App\Filament\Resources\SubmissionApplications\Pages\ManageSubmissionApplications;
use App\Jobs\CleanPhotoSubmission;
use App\Jobs\ImportPhotoSubmission;
use App\Jobs\NotifySubmissionBatch;
use App\Models\Album;
use App\Models\Badge;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoSubmission;
use App\Models\SubmissionApplication;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\ContributorService;
use App\Services\LocalPhotoDerivativeService;
use App\Services\PhotoSubmissionService;
use App\Services\StorageSettings;
use App\Services\SubmissionQualification;
use App\Services\SubmissionStorage;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Qcloud\Cos\Client;
use Tests\TestCase;

class PhotoSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
    }

    private function user(string $role = 'submitter'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function metadata(array $extra = []): array
    {
        return array_replace(['title' => '梅西训练影像', 'event_date' => null, 'source_url' => null, 'source_note' => '不详', 'description' => '用户提交说明', 'show_credit' => false, 'album_id' => null, 'category_ids' => [], 'tag_ids' => []], $extra);
    }

    private function uploaded(User $user, ?string $batch = null, int $width = 30): PhotoSubmission
    {
        return app(PhotoSubmissionService::class)->upload($user, UploadedFile::fake()->image('source.jpg', $width, 20), (string) Str::uuid(), $batch ?? (string) Str::uuid());
    }

    private function pending(User $user, array $metadata = []): PhotoSubmission
    {
        $row = $this->uploaded($user);
        app(PhotoSubmissionService::class)->submit($user, [['id' => $row->id, 'metadata' => $this->metadata($metadata)]]);

        return $row->fresh();
    }

    private function review(PhotoSubmission $row, User $reviewer, string $decision = 'accepted', ?int $replacement = null): void
    {
        app(PhotoSubmissionService::class)->review($row, $reviewer, $decision, '审核结果说明', $row->revision, replacement: $replacement);
    }

    public function test_normal_user_requires_application_and_submitter_has_no_admin_access(): void
    {
        $user = $this->user('user');
        $this->actingAs($user)->get('/me/submissions')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Me/Submissions')->where('me.can_submit', false));
        $this->post('/me/submissions/upload', ['id' => (string) Str::uuid(), 'batch_id' => (string) Str::uuid(), 'file' => UploadedFile::fake()->image('source.jpg')])->assertForbidden();
        $this->post('/me/submissions/apply', ['reason' => '我希望整理和提供相关比赛影像资料。'])->assertRedirect();
        $this->post('/me/submissions/apply', ['reason' => '我希望整理和提供相关比赛影像资料。'])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('submission_applications', 1);
        $admin = $this->user('admin');
        $application = SubmissionApplication::query()->firstOrFail();
        $this->actingAs($admin);
        Livewire::test(ManageSubmissionApplications::class)->callAction(TestAction::make('approved')->table($application), data: ['note' => '同意申请'])->assertHasNoActionErrors();
        $this->assertSame('submitter', $user->fresh()->role);
        $this->actingAs($user->fresh())->get('/admin')->assertForbidden();
        app(SubmissionQualification::class)->review($application->fresh(), $admin, 'revoked', '撤销测试');
        $this->assertSame('user', $user->fresh()->role);
        $this->assertSame(2, $user->notifications()->count());
    }

    public function test_editor_cannot_approve_qualifications_and_promotion_is_not_downgraded_by_revocation(): void
    {
        $user = $this->user('user');
        $qualification = app(SubmissionQualification::class);
        $application = $qualification->apply($user, '愿意提供资料和协助整理图片。');
        $this->actingAs($this->user('editor'))->get('/admin/submission-applications')->assertForbidden();
        $admin = $this->user('admin');
        $qualification->review($application, $admin, 'approved', '通过');
        $user->update(['role' => 'editor']);
        $qualification->review($application->fresh(), $admin, 'revoked', '撤销前台申请记录');
        $this->assertSame('editor', $user->fresh()->role);
    }

    public function test_private_upload_preview_requires_owner_or_reviewer_and_rejects_large_invalid_files(): void
    {
        $user = $this->user();
        $row = $this->uploaded($user);
        $this->actingAs($user)->get('/me/submissions/'.$row->id.'/image')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->user())->get('/me/submissions/'.$row->id.'/image')->assertForbidden();
        $this->actingAs($this->user('editor'))->get('/me/submissions/'.$row->id.'/image')->assertOk();
        Storage::disk('public')->assertMissing($row->temporary_key);
        Storage::disk('local')->assertExists($row->temporary_key);
        $this->actingAs($user)->postJson('/me/submissions/upload', ['id' => (string) Str::uuid(), 'batch_id' => (string) Str::uuid(), 'file' => UploadedFile::fake()->image('large.jpg')->size(5121)])->assertUnprocessable();
        $this->postJson('/me/submissions/upload', ['id' => (string) Str::uuid(), 'batch_id' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fake.jpg', 'not an image')])->assertUnprocessable();
    }

    public function test_submit_validates_source_and_hidden_album_and_classifications(): void
    {
        $user = $this->user();
        $row = $this->uploaded($user);
        $this->actingAs($user)->post('/me/submissions', ['items' => [['id' => $row->id, 'metadata' => $this->metadata(['source_note' => ''])]]])->assertSessionHasErrors('source_note');
        $album = Album::query()->create(['title' => '私有', 'slug' => 'private', 'status' => 'draft']);
        $this->post('/me/submissions', ['items' => [['id' => $row->id, 'metadata' => $this->metadata(['album_id' => $album->id])]]])->assertSessionHasErrors('album_id');
        $this->post('/me/submissions', ['items' => [['id' => $row->id, 'metadata' => $this->metadata()]]])->assertRedirect();
        $this->assertSame('pending', $row->fresh()->status);
        $this->assertDatabaseCount('photos', 0);
        $this->assertDatabaseCount('photo_submissions', 1);
    }

    public function test_edit_revision_blocks_stale_review_and_owner_can_withdraw_pending(): void
    {
        $user = $this->user();
        $row = $this->pending($user);
        app(PhotoSubmissionService::class)->edit($row, $user, $this->metadata(['title' => '修正标题']), $row->revision);
        try {
            $this->review($row, $this->user('editor'));
            $this->fail('不应使用过期稿件审核');
        } catch (ValidationException) {
            $this->assertSame('pending', $row->fresh()->status);
        }
        $this->actingAs($user)->post('/me/submissions/'.$row->id.'/withdraw')->assertRedirect();
        $this->assertSame('withdrawn', $row->fresh()->status);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $row->fresh()->cleanup_at->timestamp, 2);
    }

    public function test_real_import_creates_only_one_draft_with_metadata_and_never_repeats_contribution(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $album = Album::query()->create(['title' => '公开相册', 'slug' => 'public', 'status' => 'published']);
        $row = $this->pending($user, ['album_id' => $album->id]);
        $this->review($row, $editor);
        $job = new ImportPhotoSubmission($row->id);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        $row->refresh();
        $this->assertSame('accepted', $row->status);
        $this->assertDatabaseCount('photos', 1);
        $this->assertSame('draft', $row->photo->status);
        $this->assertSame('梅西训练影像', $row->photo->title);
        $this->assertTrue($row->photo->albums()->whereKey($album->id)->exists());
        $this->assertSame(1, app(BadgeService::class)->contributionCount($user));
        Storage::disk('public')->assertExists($row->photo->display_key);
        Storage::disk('local')->assertExists($row->temporary_key);
    }

    public function test_exact_duplicate_reviews_are_rejected_even_before_first_import_completes(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $first = $this->pending($user);
        $second = $this->pending($this->user());
        $second->update(['sha256' => $first->sha256]);
        $this->review($first, $editor);
        $this->expectException(ValidationException::class);
        $this->review($second, $editor);
    }

    public function test_high_resolution_replacement_preserves_photo_id_and_interactions(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $photo = Photo::query()->create(['title' => '已有图', 'width' => 10, 'height' => 10, 'status' => 'published']);
        $photo->favorites()->create(['user_id' => $user->id]);
        $row = $this->pending($user);
        $this->review($row, $editor, replacement: $photo->id);
        app()->call([new ImportPhotoSubmission($row->id), 'handle']);
        $this->assertDatabaseCount('photos', 1);
        $this->assertSame($photo->id, $row->fresh()->photo_id);
        $this->assertSame(1, $photo->favorites()->count());
        $this->assertSame('published', $photo->fresh()->status);
        $this->assertSame(30, $photo->fresh()->width);
    }

    public function test_cleanup_boundary_preserves_pending_failed_and_library_and_deletes_only_expired_private_files(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $rejected = $this->pending($user);
        $this->review($rejected, $editor, 'rejected');
        app()->call([new CleanPhotoSubmission($rejected->id), 'handle']);
        Storage::disk('local')->assertExists($rejected->temporary_key);
        $pending = $this->pending($this->user());
        $failed = $this->pending($this->user());
        $failed->update(['status' => 'failed']);
        Storage::disk('public')->put('photos/originals/keep.jpg', 'keep');
        $this->travel(7)->days();
        app()->call([new CleanPhotoSubmission($rejected->id), 'handle']);
        app()->call([new CleanPhotoSubmission($pending->id), 'handle']);
        app()->call([new CleanPhotoSubmission($failed->id), 'handle']);
        Storage::disk('local')->assertMissing($rejected->temporary_key);
        Storage::disk('local')->assertExists($pending->temporary_key);
        Storage::disk('local')->assertExists($failed->temporary_key);
        Storage::disk('public')->assertExists('photos/originals/keep.jpg');
        $this->assertNotNull($rejected->fresh()->cleaned_at);
        $this->assertSame('审核结果说明', $rejected->fresh()->review_note);
    }

    public function test_fifty_accepted_images_award_badge_once_and_revocation_stays_revoked(): void
    {
        $user = $this->user();
        $badge = Badge::query()->create(['name' => '投稿贡献', 'slug' => 'submission-contributor', 'rule_type' => 'contributions_count']);
        $template = $this->pending($user);
        for ($i = 0; $i < 49; $i++) {
            $row = $template->replicate();
            $row->id = (string) Str::uuid();
            $row->status = 'accepted';
            $row->accepted_at = now();
            $row->save();
        }
        $badges = app(BadgeService::class);
        $badges->evaluate($user);
        $this->assertDatabaseCount('user_badges', 0);
        $template->update(['status' => 'accepted', 'accepted_at' => now()]);
        $badges->evaluate($user);
        $badges->evaluate($user);
        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id, 'badge_id' => $badge->id, 'equipped' => false]);
        $this->assertDatabaseCount('notifications', 1);
        $badges->revoke($user->userBadges()->firstOrFail(), $this->user('admin'), '误发');
        $badges->evaluate($user);
        $this->assertSame('revoked', $user->userBadges()->first()->status);
    }

    public function test_admin_and_editor_can_review_but_normal_accounts_are_denied_and_internals_are_not_public(): void
    {
        $editor = $this->user('editor');
        $row = $this->pending($this->user());
        $this->actingAs($editor);
        Livewire::test(ManagePhotoSubmissions::class)->assertCanSeeTableRecords([$row])
            ->callAction(TestAction::make('rejected')->table($row), data: ['note' => '不符合收录要求', 'revision' => $row->revision])->assertHasNoActionErrors();
        $this->assertSame('rejected', $row->fresh()->status);
        $row->update(['internal_note' => '后台秘密说明']);
        $this->actingAs($row->user)->get('/me/submissions')->assertOk()->assertDontSee('后台秘密说明')->assertDontSee($row->temporary_key);
        $this->actingAs($this->user())->get('/admin/photo-submissions')->assertForbidden();
    }

    public function test_batch_notification_is_aggregated_and_idempotent(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $row = $this->pending($user);
        $this->review($row, $editor, 'rejected');
        (new NotifySubmissionBatch($row->batch_id, $user->id))->handle();
        (new NotifySubmissionBatch($row->batch_id, $user->id))->handle();
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_upload_retry_is_idempotent_and_server_asset_uuid_cannot_target_existing_library_files(): void
    {
        $user = $this->user();
        $photo = Photo::query()->create(['title' => '保护的旧图']);
        Storage::disk('public')->put('photos/derived/'.$photo->uuid.'/display.webp', 'old-content');
        $file = UploadedFile::fake()->image('source.jpg', 40, 30);
        $batch = (string) Str::uuid();
        $service = app(PhotoSubmissionService::class);
        $row = $service->upload($user, $file, $photo->uuid, $batch);
        $same = $service->upload($user, $file, $photo->uuid, $batch);
        $this->assertSame($row->id, $same->id);
        $this->assertNotSame($photo->uuid, $row->asset_uuid);
        $this->assertDatabaseCount('photo_submissions', 1);
        $service->submit($user, [['id' => $row->id, 'metadata' => $this->metadata()]]);
        $this->review($row->fresh(), $this->user('editor'));
        app()->call([new ImportPhotoSubmission($row->id), 'handle']);
        $this->assertSame('old-content', Storage::disk('public')->get('photos/derived/'.$photo->uuid.'/display.webp'));
    }

    public function test_daily_success_limit_uses_beijing_day_and_batch_limit_is_enforced(): void
    {
        $user = $this->user();
        $row = $this->uploaded($user);
        $this->travelTo(Carbon::parse('2026-10-20 17:00:00', 'Asia/Shanghai'));
        for ($i = 0; $i < 100; $i++) {
            $copy = $row->replicate();
            $copy->id = (string) Str::uuid();
            $copy->submitted_at = now();
            $copy->status = 'withdrawn';
            $copy->save();
        }
        try {
            app(PhotoSubmissionService::class)->submit($user, [['id' => $row->id, 'metadata' => $this->metadata()]]);
            $this->fail('应达到每日限额');
        } catch (ValidationException) {
            $this->assertNull($row->fresh()->submitted_at);
        }
        $this->travelTo(Carbon::parse('2026-10-21 00:00:01', 'Asia/Shanghai'));
        app(PhotoSubmissionService::class)->submit($user, [['id' => $row->id, 'metadata' => $this->metadata()]]);
        $this->assertNotNull($row->fresh()->submitted_at);
        $batch = (string) Str::uuid();
        for ($i = 0; $i < 20; $i++) {
            $copy = $row->replicate();
            $copy->id = (string) Str::uuid();
            $copy->batch_id = $batch;
            $copy->status = 'pending';
            $copy->save();
        }
        $this->expectException(ValidationException::class);
        $this->uploaded($user, $batch);
    }

    public function test_failure_retains_private_source_and_reviewer_can_retry_without_duplicate_library_record(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $row = $this->pending($user);
        $this->review($row, $editor);
        $job = new ImportPhotoSubmission($row->id);
        $job->failed(new \RuntimeException('模拟队列故障'));
        $this->assertSame('failed', $row->fresh()->status);
        $this->assertNull($row->fresh()->cleanup_at);
        Storage::disk('local')->assertExists($row->temporary_key);
        app(PhotoSubmissionService::class)->retry($row->fresh(), $editor);
        app()->call([new ImportPhotoSubmission($row->id), 'handle']);
        $this->assertSame('accepted', $row->fresh()->status);
        $this->assertDatabaseCount('photos', 1);
    }

    public function test_editor_whose_permission_was_revoked_cannot_continue_importing(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $row = $this->pending($user);
        $this->review($row, $editor);
        $editor->update(['role' => 'user']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('审核人员权限已失效');
        app()->call([new ImportPhotoSubmission($row->id), 'handle']);
    }

    public function test_real_database_worker_imports_and_cleanup_command_preserves_the_library(): void
    {
        Queue::swap(app('queue'));
        // 使用真实 QueueManager，避免仅验证派发而没有实际消费。
        app()->forgetInstance('queue');
        Queue::clearResolvedInstance('queue');
        config(['queue.default' => 'database']);
        $user = $this->user();
        $editor = $this->user('editor');
        $row = $this->pending($user);
        $this->review($row, $editor);
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1, '--timeout' => 120])->assertExitCode(0);
        $this->assertSame('accepted', $row->fresh()->status);
        $this->artisan('submissions:cleanup')->assertExitCode(0);
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1, '--timeout' => 120])->assertExitCode(0);
        Storage::disk('local')->assertMissing($row->temporary_key);
        Storage::disk('public')->assertExists($row->fresh()->photo->original_key);
        $this->assertNotNull($row->fresh()->cleaned_at);
    }

    public function test_cos_upload_checks_privacy_before_sending_image_and_tracks_object_version(): void
    {
        $row = $this->uploaded($this->user());
        $row->update(['storage_driver' => 'cos', 'storage_location' => ['bucket' => 'example-1250000000', 'region' => 'ap-guangzhou']]);
        $settings = \Mockery::mock(StorageSettings::class);
        $settings->shouldReceive('credentials')->andReturn(['bucket' => 'example-1250000000', 'region' => 'ap-guangzhou', 'secret_id' => 'test-id', 'secret_key' => 'test-key']);
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('putObject')->once()->withArgs(fn ($args) => $args['ACL'] === 'private' && $args['Body'] === 'private-access-probe')->andReturn(['VersionId' => 'probe-version']);
        $client->shouldReceive('putObject')->once()->withArgs(fn ($args) => $args['ACL'] === 'private' && $args['ContentType'] === 'image/jpeg' && $args['Body'] !== 'private-access-probe')->andReturn(['VersionId' => 'image-version']);
        Http::fake(['*' => Http::response('Forbidden', 403)]);
        (new SubmissionStorage($settings, $client))->put($row, UploadedFile::fake()->image('cos.jpg'));
        $this->assertSame('image-version', $row->fresh()->version_id);
    }

    public function test_cos_public_policy_never_receives_real_image_and_is_rejected(): void
    {
        $row = $this->uploaded($this->user());
        $row->update(['storage_driver' => 'cos', 'storage_location' => ['bucket' => 'example-1250000000', 'region' => 'ap-guangzhou']]);
        $settings = \Mockery::mock(StorageSettings::class);
        $settings->shouldReceive('credentials')->andReturn(['bucket' => 'example-1250000000', 'region' => 'ap-guangzhou']);
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('putObject')->once()->withArgs(fn ($args) => $args['Body'] === 'private-access-probe')->andReturn(['VersionId' => 'probe-version']);
        Http::fake(['*' => Http::response('private-access-probe', 206)]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未通过私有访问检查');
        (new SubmissionStorage($settings, $client))->put($row, UploadedFile::fake()->image('cos.jpg'));
    }

    public function test_cos_cleanup_deletes_exact_versions_and_markers_without_touching_other_objects(): void
    {
        $row = $this->uploaded($this->user());
        $row->update(['storage_driver' => 'cos', 'storage_location' => ['bucket' => 'example-1250000000', 'region' => 'ap-guangzhou']]);
        $settings = \Mockery::mock(StorageSettings::class);
        $settings->shouldReceive('credentials')->andReturn(['bucket' => 'example-1250000000', 'region' => 'ap-guangzhou']);
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('getBucketVersioning')->once()->andReturn(['Status' => 'Enabled']);
        $client->shouldReceive('listObjectVersions')->twice()->andReturn([
            'Version' => [['Key' => $row->temporary_key, 'VersionId' => 'v1'], ['Key' => $row->temporary_key, 'VersionId' => 'v2'], ['Key' => 'photos/originals/protected', 'VersionId' => 'keep']],
            'DeleteMarkers' => [['Key' => $row->temporary_key, 'VersionId' => 'marker']],
        ], ['Version' => [], 'DeleteMarkers' => []]);
        foreach (['v1', 'v2', 'marker'] as $version) {
            $client->shouldReceive('deleteObject')->once()->withArgs(fn ($args) => $args['Key'] === $row->temporary_key && $args['VersionId'] === $version)->andReturn([]);
        }
        (new SubmissionStorage($settings, $client))->delete($row);
        $this->assertTrue(true);
    }

    public function test_formal_library_paths_cannot_be_passed_to_private_cleanup(): void
    {
        $row = $this->uploaded($this->user());
        $row->update(['temporary_key' => 'photos/originals/protected.jpg']);
        Storage::disk('local')->put('photos/originals/protected.jpg', 'keep');
        try {
            app(SubmissionStorage::class)->delete($row);
            $this->fail('应拒绝跨区域删除');
        } catch (\RuntimeException) {
            Storage::disk('local')->assertExists('photos/originals/protected.jpg');
        }
    }

    public function test_public_attribution_respects_opt_in_profile_switch_and_never_discloses_private_fields(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $row = $this->pending($user, ['show_credit' => true]);
        $this->review($row, $editor);
        app()->call([new ImportPhotoSubmission($row->id), 'handle']);
        $row->fresh()->photo->update(['status' => 'published', 'copyright_status' => 'credited']);
        $url = '/photos/'.$row->fresh()->photo->uuid;
        $this->get($url)->assertOk()->assertDontSee($user->email)->assertDontSee($row->temporary_key)
            ->assertInertia(fn (Assert $page) => $page->where('photoDetail.photo.submission.credit.name', $user->name)->where('photoDetail.photo.submission.credit.url', null));
        $user->update(['profile_public' => true]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('photoDetail.photo.submission.credit.url', '/users/'.$user->id));
        $row->update(['show_credit' => false]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('photoDetail.photo.submission.credit', null));
    }

    public function test_editor_can_correct_metadata_and_existing_category_selection_is_saved(): void
    {
        $editor = $this->user('editor');
        $row = $this->pending($this->user());
        $parent = Category::query()->create(['name' => '阶段', 'slug' => 'stage', 'visibility' => 'public']);
        $child = Category::query()->create(['name' => '巴萨', 'slug' => 'barca', 'parent_id' => $parent->id, 'visibility' => 'public']);
        $this->actingAs($editor);
        Livewire::test(ManagePhotoSubmissions::class)->callAction(TestAction::make('edit')->table($row), data: [
            'metadata' => $this->metadata(['category_ids' => [$child->id], 'title' => '审核修正标题']), 'revision' => $row->revision,
            'internal_note' => '内部整理备注',
        ])->assertHasNoActionErrors();
        $this->assertSame('审核修正标题', $row->fresh()->metadata['title']);
        $this->review($row->fresh(), $editor);
        app()->call([new ImportPhotoSubmission($row->id), 'handle']);
        $this->assertTrue($row->fresh()->photo->categories()->whereKey($child->id)->exists());
    }

    public function test_bulk_review_uses_revision_snapshot_and_skips_changed_submission(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $row = $this->pending($user);
        $revision = $row->revision;
        app(PhotoSubmissionService::class)->edit($row, $user, $this->metadata(['title' => '用户刚修改']), $revision);
        $this->actingAs($editor);
        Livewire::test(ManagePhotoSubmissions::class)->selectTableRecords([$row->id])
            ->callAction(TestAction::make('accepted')->table()->bulk(), data: ['note' => '批量采纳', 'revisions' => json_encode([$row->id => $revision])])
            ->assertHasNoActionErrors();
        $this->assertSame('pending', $row->fresh()->status);
        $this->assertDatabaseCount('photo_submission_claims', 0);
    }

    public function test_partial_import_failure_cleans_unadopted_library_assets_but_retains_private_source(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $row = $this->pending($user);
        $this->review($row, $editor);
        $this->mock(LocalPhotoDerivativeService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once()->andThrow(new \RuntimeException('模拟派生处理失败'));
        });
        $job = new ImportPhotoSubmission($row->id);
        try {
            app()->call([$job, 'handle']);
            $this->fail('应模拟处理失败');
        } catch (\RuntimeException $error) {
            $job->failed($error);
        }
        $this->assertSame('failed', $row->fresh()->status);
        Storage::disk('local')->assertExists($row->temporary_key);
        Storage::disk('public')->assertMissing('photos/originals/submissions/'.$row->asset_uuid.'.jpg');
        $this->assertDatabaseCount('photos', 0);
        $this->review($row->fresh(), $editor, 'rejected');
        $this->assertSame('rejected', $row->fresh()->status);
        $this->assertDatabaseCount('photo_submission_claims', 0);
    }

    public function test_temporary_upload_expires_after_24_hours_and_cleanup_failure_is_retryable(): void
    {
        $row = $this->uploaded($this->user());
        $this->travel(24)->hours();
        $broken = \Mockery::mock(SubmissionStorage::class);
        $broken->shouldReceive('delete')->once()->andThrow(new \RuntimeException('删除服务暂不可用'));
        $job = new CleanPhotoSubmission($row->id);
        try {
            $job->handle($broken, app(PhotoSubmissionService::class));
            $this->fail('应模拟删除失败');
        } catch (\RuntimeException $error) {
            $job->failed($error);
        }
        $this->assertNull($row->fresh()->cleaned_at);
        Storage::disk('local')->assertExists($row->temporary_key);
        app()->call([$job, 'handle']);
        $this->assertNotNull($row->fresh()->cleaned_at);
        $this->assertSame('withdrawn', $row->fresh()->status);
    }

    public function test_submitter_promoted_to_contributor_restores_qualification_safely(): void
    {
        $admin = $this->user('admin');
        $user = $this->user('user');
        $qualification = app(SubmissionQualification::class);
        $application = $qualification->apply($user, '希望提供和整理更多历史图片。');
        $qualification->review($application, $admin, 'approved', '同意申请');
        $contributors = app(ContributorService::class);
        $contributors->grant($user->fresh(), $admin, []);
        $this->assertSame('editor', $user->fresh()->role);
        $qualification->review($application->fresh(), $admin, 'revoked', '撤销前台资格，不修改编辑身份');
        $this->assertSame('editor', $user->fresh()->role);
        $contributors->revoke($user->fresh(), $admin);
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_reviewer_cannot_opt_user_into_public_attribution_and_can_revoke_only_contribution_count(): void
    {
        $user = $this->user();
        $editor = $this->user('editor');
        $row = $this->pending($user);
        app(PhotoSubmissionService::class)->amend($row, $editor, ['metadata' => $this->metadata(['show_credit' => true]), 'revision' => $row->revision]);
        $this->assertFalse($row->fresh()->show_credit);
        $this->review($row->fresh(), $editor);
        app()->call([new ImportPhotoSubmission($row->id), 'handle']);
        app(PhotoSubmissionService::class)->revokeContribution($row->fresh(), $this->user('admin'), '误采纳纠正');
        $this->assertSame(0, app(BadgeService::class)->contributionCount($user));
        $this->assertSame('accepted', $row->fresh()->status);
        $this->assertDatabaseCount('photos', 1);
    }
}
