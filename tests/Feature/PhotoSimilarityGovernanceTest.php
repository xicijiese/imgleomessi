<?php

namespace Tests\Feature;

use App\Filament\Resources\PhotoDeduplicationLogs\PhotoDeduplicationLogResource;
use App\Filament\Resources\PhotoGovernanceRuns\PhotoGovernanceRunResource;
use App\Filament\Resources\PhotoSimilarityGroups\Pages\ManagePhotoSimilarityGroups;
use App\Filament\Resources\PhotoSimilarityGroups\PhotoSimilarityGroupResource;
use App\Jobs\ProcessPhotoGovernance;
use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoDeduplicationLog;
use App\Models\PhotoGovernanceRun;
use App\Models\PhotoSimilarityCandidate;
use App\Models\PhotoSimilarityGroup;
use App\Models\User;
use App\Services\PhotoSimilarityGovernance;
use App\Services\PhotoSimilarityService;
use App\Services\QueueWorkerStatus;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PhotoSimilarityGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_similarity_management_switches_between_groups_candidates_runs_and_logs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $a = $this->photo(['title' => '审核图片 A']);
        $b = $this->photo(['title' => '审核图片 B']);
        $this->pair($a, $b);
        app(PhotoSimilarityGovernance::class)->rebuildGroups();
        $page = Livewire::test(ManagePhotoSimilarityGroups::class)
            ->assertSee('图片分组')->assertSee('逐对审核')->assertSee('处理进度')->assertSee('归档记录');
        $page->set('activeTab', 'candidates')->assertCanSeeTableRecords(PhotoSimilarityCandidate::all())
            ->callAction(TestAction::make('keepSeparate')->table(PhotoSimilarityCandidate::firstOrFail()));
        $this->assertDatabaseHas('photo_similarity_candidates', ['status' => 'kept_separate']);
        $this->assertDatabaseCount('photo_similarity_groups', 0);
        $page->set('activeTab', 'runs')->assertSee('处理结果');
        $page->set('activeTab', 'logs')->assertSee('归档图片');
        $page->set('activeTab', 'groups')->assertSee('组号');
        $this->assertFalse(PhotoGovernanceRunResource::shouldRegisterNavigation());
        $this->assertFalse(PhotoDeduplicationLogResource::shouldRegisterNavigation());
    }

    public function test_offline_worker_is_reported_and_duplicate_submissions_are_not_enqueued(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $page = Livewire::test(ManagePhotoSimilarityGroups::class)->assertSee('后台处理服务未连接');
        $page->callAction('groups')->callAction('groups');
        Queue::assertPushed(ProcessPhotoGovernance::class, 1);
        $this->assertDatabaseCount('photo_governance_runs', 1);
    }

    public function test_real_database_worker_consumes_queued_analysis_and_reports_heartbeat(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('source.jpg', 20, 20);
        Storage::disk('public')->put('source.jpg', file_get_contents($file->getRealPath()));
        $this->photo(['original_key' => 'source.jpg']);
        $this->photo(['original_key' => 'source.jpg']);
        $run = PhotoGovernanceRun::query()->create(['type' => 'groups', 'operated_by' => User::factory()->create(['role' => 'admin', 'status' => 'active'])->id]);
        ProcessPhotoGovernance::dispatch($run->id);
        $this->assertSame('pending', $run->fresh()->status);
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1, '--timeout' => 120])->assertExitCode(0);
        $this->assertSame('done', $run->fresh()->status);
        $this->assertSame(1, $run->fresh()->result_count);
        $this->assertTrue(app(QueueWorkerStatus::class)->available());
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_worker_status_requires_matching_connection_queue_and_recent_heartbeat(): void
    {
        config(['queue.default' => 'database']);
        $status = app(QueueWorkerStatus::class);
        Event::dispatch(new Looping('redis', 'default'));
        Event::dispatch(new Looping('database', 'emails'));
        $this->assertFalse($status->available());
        Event::dispatch(new Looping('database', 'default,emails'));
        $this->assertTrue($status->available());
        $this->travel(181)->seconds();
        $this->assertFalse($status->available());
    }

    public function test_grouping_does_not_chain_unrelated_photos_and_is_repeatable(): void
    {
        [$a, $b, $c] = [$this->photo(), $this->photo(), $this->photo()];
        $this->pair($a, $b);
        $this->pair($b, $c);
        $service = app(PhotoSimilarityGovernance::class);
        $this->assertSame(1, $service->rebuildGroups());
        $this->assertSame([$a->id, $b->id], PhotoSimilarityGroup::query()->firstOrFail()->photos()->orderBy('photos.id')->pluck('photos.id')->all());
        $this->assertSame(1, $service->rebuildGroups());
        $this->assertDatabaseCount('photo_similarity_group_members', 2);
    }

    public function test_manual_split_survives_regrouping(): void
    {
        [$a, $b, $c] = [$this->photo(), $this->photo(), $this->photo()];
        $this->pair($a, $b);
        $this->pair($a, $c);
        $this->pair($b, $c);
        $service = app(PhotoSimilarityGovernance::class);
        $service->rebuildGroups();
        $group = PhotoSimilarityGroup::query()->firstOrFail();
        $service->separate($group, $a->id, User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $service->rebuildGroups();
        $this->assertSame([$b->id, $c->id], PhotoSimilarityGroup::query()->firstOrFail()->photos()->orderBy('photos.id')->pluck('photos.id')->all());
        $this->assertDatabaseMissing('photo_similarity_group_members', ['photo_id' => $a->id]);
    }

    public function test_ignored_and_archived_photos_do_not_form_groups(): void
    {
        $a = $this->photo();
        $b = $this->photo();
        $archived = $this->photo(['status' => 'archived']);
        $this->pair($a, $b, 'ignored');
        $this->pair($a, $archived);
        $this->assertSame(0, app(PhotoSimilarityGovernance::class)->rebuildGroups());
    }

    public function test_exact_deduplication_prefers_published_photo_and_preserves_files_and_relations(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('original.jpg', 'binary');
        $draft = $this->photo(['status' => 'draft', 'original_key' => 'original.jpg']);
        $published = $this->photo(['original_key' => 'original.jpg']);
        $different = $this->photo();
        foreach ([$draft, $published] as $photo) {
            PhotoAnalysisResult::query()->create(['photo_id' => $photo->id, 'sha256_hash' => hash('sha256', 'same')]);
        }
        PhotoAnalysisResult::query()->create(['photo_id' => $different->id, 'sha256_hash' => hash('sha256', 'different'), 'perceptual_hash' => '0000000000000000']);
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $draft->favorites()->create(['user_id' => $user->id]);
        $service = app(PhotoSimilarityGovernance::class);
        $this->assertSame(1, $service->deduplicate($user->id));
        $this->assertSame('published', $published->fresh()->status);
        $this->assertSame('archived', $draft->fresh()->status);
        $this->assertSame('published', $different->fresh()->status);
        $this->assertSame(1, $draft->favorites()->count());
        Storage::disk('public')->assertExists('original.jpg');
        $this->assertSame(0, $service->deduplicate($user->id));
        $log = PhotoDeduplicationLog::query()->firstOrFail();
        $this->assertSame($published->id, $log->retained_photo_id);
        $service->restore($log, $user);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertNull($draft->fresh()->published_at);
        $this->assertSame(0, $service->deduplicate($user->id));
        $this->assertDatabaseCount('photo_deduplication_logs', 1);
    }

    public function test_restricted_photos_are_not_selected_as_retained_or_archived(): void
    {
        $restricted = $this->photo(['copyright_status' => 'restricted']);
        $public = $this->photo();
        foreach ([$restricted, $public] as $photo) {
            PhotoAnalysisResult::query()->create(['photo_id' => $photo->id, 'sha256_hash' => hash('sha256', 'same')]);
        }
        $this->assertSame(0, app(PhotoSimilarityGovernance::class)->deduplicate(null));
        $this->assertSame('published', $restricted->fresh()->status);
    }

    public function test_admin_can_open_governance_pages_but_users_and_editors_cannot(): void
    {
        $a = $this->photo();
        $b = $this->photo();
        $this->pair($a, $b);
        app(PhotoSimilarityGovernance::class)->rebuildGroups();
        PhotoGovernanceRun::query()->create(['type' => 'groups']);
        PhotoDeduplicationLog::query()->create(['photo_id' => $b->id, 'retained_photo_id' => $a->id, 'sha256_hash' => hash('sha256', 'same'), 'previous_status' => 'published']);
        $resources = [PhotoSimilarityGroupResource::class, PhotoDeduplicationLogResource::class, PhotoGovernanceRunResource::class];
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        foreach ($resources as $resource) {
            $this->followingRedirects()->get($resource::getUrl())->assertOk();
        }
        foreach (['user', 'editor'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']));
            foreach ($resources as $resource) {
                $this->get($resource::getUrl())->assertForbidden();
            }
        }
    }

    public function test_admin_submits_background_task_from_group_page(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        Livewire::test(ManagePhotoSimilarityGroups::class)
            ->callAction('groups');
        Queue::assertPushed(ProcessPhotoGovernance::class);
        $this->assertDatabaseHas('photo_governance_runs', ['type' => 'groups', 'status' => 'pending']);
    }

    public function test_background_analysis_builds_candidates_and_groups(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('source.jpg', 30, 20);
        Storage::disk('public')->put('a.jpg', file_get_contents($file->getRealPath()));
        Storage::disk('public')->put('b.jpg', file_get_contents($file->getRealPath()));
        $this->photo(['original_key' => 'a.jpg']);
        $this->photo(['original_key' => 'b.jpg']);
        $run = PhotoGovernanceRun::query()->create(['type' => 'groups', 'operated_by' => User::factory()->create(['role' => 'admin', 'status' => 'active'])->id]);
        $job = new ProcessPhotoGovernance($run->id);
        $job->handle(app(PhotoSimilarityGovernance::class), app(PhotoSimilarityService::class));
        $this->assertSame('done', $run->fresh()->status);
        $this->assertSame(1, $run->fresh()->result_count);
        $this->assertDatabaseCount('photo_similarity_groups', 1);
    }

    public function test_large_analysis_resumes_from_saved_cursor(): void
    {
        Queue::fake();
        Storage::fake('public');
        $file = UploadedFile::fake()->image('source.jpg', 20, 20);
        Storage::disk('public')->put('source.jpg', file_get_contents($file->getRealPath()));
        for ($i = 0; $i < 11; $i++) {
            $this->photo(['original_key' => 'source.jpg']);
        }
        $run = PhotoGovernanceRun::query()->create(['type' => 'groups', 'operated_by' => User::factory()->create(['role' => 'admin', 'status' => 'active'])->id]);
        $job = new ProcessPhotoGovernance($run->id);
        $job->handle(app(PhotoSimilarityGovernance::class), app(PhotoSimilarityService::class));
        $this->assertSame('running', $run->fresh()->status);
        $this->assertSame(10, $run->fresh()->last_photo_id);
        Queue::assertPushed(ProcessPhotoGovernance::class);
        $job->handle(app(PhotoSimilarityGovernance::class), app(PhotoSimilarityService::class));
        $this->assertSame('done', $run->fresh()->status);
        $this->assertDatabaseCount('photo_similarity_group_members', 11);
    }

    public function test_worker_rechecks_operator_permissions_before_archiving(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $run = PhotoGovernanceRun::query()->create(['type' => 'deduplicate', 'operated_by' => $user->id]);
        (new ProcessPhotoGovernance($run->id))->handle(app(PhotoSimilarityGovernance::class), app(PhotoSimilarityService::class));
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertDatabaseCount('photo_deduplication_logs', 0);
    }

    public function test_admin_can_compare_and_split_group_from_table(): void
    {
        [$a, $b] = [$this->photo(), $this->photo()];
        $this->pair($a, $b);
        app(PhotoSimilarityGovernance::class)->rebuildGroups();
        $group = PhotoSimilarityGroup::query()->firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        Livewire::test(ManagePhotoSimilarityGroups::class)
            ->mountAction(TestAction::make('compare')->table($group))
            ->assertActionMounted(TestAction::make('compare')->table($group));
        Livewire::test(ManagePhotoSimilarityGroups::class)
            ->callAction(TestAction::make('separate')->table($group), ['photo_id' => $a->id]);
        $this->assertDatabaseCount('photo_similarity_groups', 0);
        $this->assertDatabaseHas('photo_similarity_candidates', ['photo_id' => $a->id, 'candidate_photo_id' => $b->id, 'status' => 'kept_separate']);
    }

    private function photo(array $attributes = []): Photo
    {
        return Photo::query()->create(array_merge(['title' => '测试图片', 'status' => 'published', 'published_at' => now()], $attributes));
    }

    private function pair(Photo $a, Photo $b, string $status = 'pending_review'): void
    {
        PhotoSimilarityCandidate::query()->create(['photo_id' => min($a->id, $b->id), 'candidate_photo_id' => max($a->id, $b->id), 'distance' => 2, 'similarity_score' => 96, 'status' => $status]);
    }
}
