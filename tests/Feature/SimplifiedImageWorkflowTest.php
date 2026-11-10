<?php

namespace Tests\Feature;

use App\Filament\Resources\PhotoDuplicates\Pages\ManagePhotoDuplicates;
use App\Filament\Resources\PhotoDuplicates\PhotoDuplicateResource;
use App\Jobs\ProcessPhotoAnalysisJob;
use App\Jobs\ProcessPhotoGovernance;
use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoDeduplicationLog;
use App\Models\PhotoGovernanceRun;
use App\Models\PhotoSimilarityCandidate;
use App\Models\Tag;
use App\Models\User;
use App\Services\PhotoProcessingService;
use App\Services\PhotoSimilarityGovernance;
use App\Services\PhotoSimilarityService;
use App\Services\PhotoUploadService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class SimplifiedImageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_hash_completion_queues_duplicate_matching_without_driving_tag_suggestions(): void
    {
        Storage::fake('public');
        Queue::fake();
        $upload = app(PhotoUploadService::class);
        $a = $upload->store(UploadedFile::fake()->image('a.jpg', 120, 80), attributes: ['status' => 'published']);
        $b = $upload->store(UploadedFile::fake()->image('b.jpg', 60, 90), attributes: ['status' => 'published']);
        $processing = app(PhotoProcessingService::class);
        foreach ([$a, $b] as $photo) {
            $this->assertSame('done', $processing->process($photo->processingJobs()->where('type', 'hash')->firstOrFail())->status);
            $this->assertTrue($photo->processingJobs()->where('type', 'similarity')->where('status', 'pending')->exists());
        }
        foreach ([$a, $b] as $photo) {
            $this->assertSame('done', $processing->process($photo->processingJobs()->where('type', 'similarity')->firstOrFail())->status);
        }
        $this->assertDatabaseCount('photo_similarity_candidates', 1);
        Queue::assertPushed(ProcessPhotoAnalysisJob::class, 8);
        $a->update(['status' => 'published']);
        $b->update(['status' => 'published']);
        $this->get('/photos/'.$a->uuid)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('photoDetail.suggestions.data', 0));
        $this->assertNotSame('sync', (new ProcessPhotoAnalysisJob(1))->connection);
    }

    public function test_a_failed_hash_does_not_start_matching(): void
    {
        Storage::fake('public');
        Queue::fake();
        $photo = Photo::create(['title' => '缺少原图', 'original_key' => 'missing.jpg']);
        $service = app(PhotoProcessingService::class);
        $this->assertSame('failed', $service->process($service->createJob($photo, 'hash'))->status);
        $this->assertFalse($photo->processingJobs()->where('type', 'similarity')->exists());
    }

    public function test_duplicate_candidate_states_do_not_control_tag_suggestions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $a = Photo::create(['title' => '保留图', 'status' => 'published']);
        $b = Photo::create(['title' => '完全重复', 'status' => 'published']);
        $tag = Tag::create(['name' => '共同标签']);
        $a->tags()->sync([$tag->id]);
        $b->tags()->sync([$tag->id]);
        foreach ([$a, $b] as $photo) {
            PhotoAnalysisResult::create(['photo_id' => $photo->id, 'sha256_hash' => hash('sha256', 'identical')]);
        }
        $pair = $this->pair($a, $b);
        $this->assertTrue($pair->isExactFile());
        $this->get('/admin/photo-similarity-groups')->assertNotFound();
        $this->assertTrue(PhotoDuplicateResource::getEloquentQuery()->whereKey($pair->id)->exists());
        Livewire::test(ManagePhotoDuplicates::class)
            ->filterTable('result', 'exact')->assertCanSeeTableRecords([$pair])
            ->filterTable('result', 'similar')->assertCanNotSeeTableRecords([$pair]);
        $this->get('/photos/'.$a->uuid)->assertInertia(fn (Assert $page) => $page
            ->has('photoDetail.suggestions.data', 1)->where('photoDetail.suggestions.data.0.id', $b->id));
        $b->analysisResult->update(['sha256_hash' => hash('sha256', 'different')]);
        $pair->update(['status' => 'confirmed_duplicate']);
        $this->get('/photos/'.$a->uuid)->assertInertia(fn (Assert $page) => $page
            ->has('photoDetail.suggestions.data', 1)->where('photoDetail.suggestions.data.0.id', $b->id));
        $pair->update(['status' => 'ignored']);
        $this->assertTrue(PhotoDuplicateResource::getEloquentQuery()->whereKey($pair->id)->exists());
    }

    public function test_check_is_async_non_destructive_and_does_not_create_groups(): void
    {
        Storage::fake('public');
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $file = UploadedFile::fake()->image('same.jpg', 30, 20);
        foreach (['a.jpg', 'b.jpg'] as $key) {
            Storage::disk('public')->put($key, file_get_contents($file->getRealPath()));
            Photo::create(['title' => $key, 'original_key' => $key, 'status' => 'published']);
        }
        Livewire::test(ManagePhotoDuplicates::class)->callAction('groups')->assertHasNoErrors();
        Queue::assertPushed(ProcessPhotoGovernance::class, 1);
        $run = PhotoGovernanceRun::sole();
        $this->assertSame('duplicates', $run->type);
        $this->assertDatabaseCount('photo_similarity_candidates', 0);
        (new ProcessPhotoGovernance($run->id))->handle(app(PhotoSimilarityGovernance::class), app(PhotoSimilarityService::class));
        $this->assertSame('done', $run->fresh()->status);
        $this->assertTrue(PhotoSimilarityCandidate::sole()->isExactFile());
        $this->assertDatabaseCount('photos', 2);
        $this->assertDatabaseCount('photo_similarity_groups', 0);
        $this->assertDatabaseCount('photo_deduplication_logs', 0);
    }

    public function test_duplicate_delete_keeps_the_selected_photo_and_uses_existing_storage_policy(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $a = Photo::create(['title' => '保留', 'status' => 'published']);
        $b = Photo::create(['title' => '移除', 'status' => 'published', 'original_key' => 'retained-file.jpg']);
        Storage::disk('public')->put('retained-file.jpg', 'existing file');
        $pair = $this->pair($a, $b);
        Livewire::test(ManagePhotoDuplicates::class)
            ->callAction(TestAction::make('keepA')->table($pair))->assertHasNoActionErrors();
        $this->assertDatabaseHas('photos', ['id' => $a->id]);
        $this->assertDatabaseMissing('photos', ['id' => $b->id]);
        $this->assertDatabaseCount('photo_similarity_candidates', 0);
        Storage::disk('public')->assertExists('retained-file.jpg');
    }

    public function test_old_queued_auto_archive_tasks_no_longer_change_photos(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        foreach (['甲', '乙'] as $title) {
            $photo = Photo::create(['title' => $title, 'status' => 'published']);
            PhotoAnalysisResult::create(['photo_id' => $photo->id, 'sha256_hash' => hash('sha256', 'same')]);
        }
        $run = PhotoGovernanceRun::create(['type' => 'deduplicate', 'operated_by' => $admin->id]);
        (new ProcessPhotoGovernance($run->id))->handle(app(PhotoSimilarityGovernance::class), app(PhotoSimilarityService::class));
        $this->assertSame('done', $run->fresh()->status);
        $this->assertSame(2, Photo::published()->count());
        $this->assertDatabaseCount('photo_deduplication_logs', 0);
    }

    public function test_protected_history_blocks_deletion_and_non_admins_cannot_access_either_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $a = Photo::create(['title' => '保留', 'status' => 'published']);
        $b = Photo::create(['title' => '受保护', 'status' => 'published']);
        $pair = $this->pair($a, $b);
        PhotoDeduplicationLog::create(['photo_id' => $b->id, 'retained_photo_id' => $a->id, 'sha256_hash' => hash('sha256', 'same'), 'previous_status' => 'published']);
        Livewire::test(ManagePhotoDuplicates::class)
            ->callAction(TestAction::make('keepA')->table($pair))->assertNotified('该图片有关联的历史处理记录，不能直接删除。');
        $this->assertDatabaseCount('photos', 2);
        foreach (['editor', 'user'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']));
            $this->get(PhotoDuplicateResource::getUrl())->assertForbidden();
            $this->get('/admin/photo-similarity-groups')->assertNotFound();
        }
    }

    private function pair(Photo $a, Photo $b): PhotoSimilarityCandidate
    {
        return PhotoSimilarityCandidate::create(['photo_id' => $a->id, 'candidate_photo_id' => $b->id, 'distance' => 0, 'similarity_score' => 100]);
    }
}
