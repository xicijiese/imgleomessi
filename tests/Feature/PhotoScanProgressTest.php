<?php

namespace Tests\Feature;

use App\Filament\Resources\PhotoDuplicates\Pages\ManagePhotoDuplicates;
use App\Jobs\ProcessPhotoGovernance;
use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoGovernanceRun;
use App\Models\PhotoSimilarityCandidate;
use App\Models\User;
use App\Services\PhotoSimilarityGovernance;
use App\Services\PhotoSimilarityService;
use App\Services\QueueWorkerStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class PhotoScanProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $worker = Mockery::mock(QueueWorkerStatus::class);
        $worker->shouldReceive('available')->andReturn(false);
        $this->app->instance(QueueWorkerStatus::class, $worker);
    }

    public function test_duplicate_submit_button_opens_a_progress_modal_and_reopening_does_not_queue_again(): void
    {
        Queue::fake();
        Photo::create(['title' => '待检查']);
        Photo::create(['title' => '不检查归档图', 'status' => 'archived']);
        foreach ([ManagePhotoDuplicates::class => 'duplicates'] as $page => $type) {
            $component = Livewire::test($page)->callAction('groups')->assertActionMounted('progress');
            $run = PhotoGovernanceRun::latest('id')->firstOrFail();
            $this->assertSame($type, $run->type);
            $this->assertSame(1, $run->total_count);
            $component->assertSet('mountedActions.0.arguments.runId', $run->id)
                ->assertMountedActionModalSee('排队中')->assertMountedActionModalSee('暂未检测到后台处理服务');
            $component->call('unmountAction')->callAction('viewProgress')->assertActionMounted('progress');
            $run->update(['status' => 'done']);
        }
        Queue::assertPushed(ProcessPhotoGovernance::class, 1);
    }

    public function test_progress_stays_bound_to_the_submitted_run_and_shows_zero_result_completion(): void
    {
        Queue::fake();
        $run = PhotoGovernanceRun::create(['type' => 'duplicates', 'total_count' => 3, 'status' => 'pending']);
        $component = Livewire::test(ManagePhotoDuplicates::class)->mountAction('progress', ['runId' => $run->id]);
        $run->update(['status' => 'running', 'processed_count' => 1, 'last_photo_id' => 999]);
        PhotoGovernanceRun::create(['type' => 'duplicates', 'status' => 'done', 'result_count' => 40]);
        $component->call('$refresh')->assertMountedActionModalSee('33%')->assertMountedActionModalSee('正在处理')
            ->assertSet('mountedActions.0.arguments.runId', $run->id);
        $run->update(['status' => 'done', 'processed_count' => 3, 'result_count' => 0]);
        $component->call('$refresh')->assertMountedActionModalSee('100%')->assertMountedActionModalSee('本次任务已完成')
            ->assertMountedActionModalSee('未发现符合条件的疑似重复图片');
        $html = $component->getMountedActionModalHtml();
        $this->assertStringNotContainsString('wire:poll.2s', $html);
        Queue::assertNothingPushed();
    }

    public function test_existing_active_task_is_shown_instead_of_silently_rejecting_the_click(): void
    {
        Queue::fake();
        $run = PhotoGovernanceRun::create(['type' => 'duplicates', 'status' => 'running', 'total_count' => 10, 'processed_count' => 4]);
        $component = Livewire::test(ManagePhotoDuplicates::class)->callAction('groups')
            ->assertActionMounted('progress')->assertSet('mountedActions.0.arguments.runId', $run->id)
            ->assertMountedActionModalSee('40%')->assertMountedActionModalSee('图片查重');
        $component->call('unmountAction')->callAction('viewProgress')
            ->assertActionMounted('progress')->assertSet('mountedActions.0.arguments.runId', $run->id);
        $this->assertDatabaseCount('photo_governance_runs', 1);
        Queue::assertNothingPushed();
    }

    public function test_batch_progress_and_final_counts_match_each_pages_result_rules(): void
    {
        Queue::fake();
        $photos = collect(['甲', '乙', '丙'])->map(function (string $title): Photo {
            $photo = Photo::create(['title' => $title, 'status' => 'published']);
            PhotoAnalysisResult::create(['photo_id' => $photo->id, 'sha256_hash' => hash('sha256', $title), 'perceptual_hash' => '0000000000000000']);

            return $photo;
        });
        PhotoSimilarityCandidate::create(['photo_id' => $photos[0]->id, 'candidate_photo_id' => $photos[1]->id, 'distance' => 0, 'similarity_score' => 100, 'status' => 'ignored']);
        foreach (['duplicates' => 3] as $type => $expected) {
            $run = PhotoGovernanceRun::create(['type' => $type, 'operated_by' => auth()->id()]);
            (new ProcessPhotoGovernance($run->id))->handle(app(PhotoSimilarityGovernance::class), app(PhotoSimilarityService::class));
            $run->refresh();
            $this->assertSame('done', $run->status);
            $this->assertSame(3, $run->processed_count);
            $this->assertSame(3, $run->total_count);
            $this->assertSame($expected, $run->result_count);
            $this->assertSame(100, $run->progressPercent());
        }
    }

    public function test_image_errors_are_skipped_and_later_images_are_checked_with_named_failure_details(): void
    {
        Queue::fake();
        $a = Photo::create(['title' => '前面的正常图片']);
        $b = Photo::create(['title' => '损坏的训练照片', 'original_filename' => 'training-broken.jpg']);
        $c = Photo::create(['title' => '后面的正常图片']);
        foreach ([$a, $b, $c] as $photo) {
            PhotoAnalysisResult::create(['photo_id' => $photo->id, 'sha256_hash' => hash('sha256', $photo->title)]);
        }
        $service = Mockery::mock(PhotoSimilarityService::class);
        $service->shouldReceive('generateCandidates')->with(Mockery::on(fn (Photo $photo) => $photo->is($a)))->once()->andReturn(0);
        $service->shouldReceive('generateCandidates')->with(Mockery::on(fn (Photo $photo) => $photo->is($b)))->once()->andThrow(new \RuntimeException('无法读取图片内容，无法生成相似图特征。'));
        $service->shouldReceive('generateCandidates')->with(Mockery::on(fn (Photo $photo) => $photo->is($c)))->once()->andReturn(0);
        $run = PhotoGovernanceRun::create(['type' => 'duplicates', 'operated_by' => auth()->id()]);
        (new ProcessPhotoGovernance($run->id))->handle(app(PhotoSimilarityGovernance::class), $service);
        $run->refresh();
        $this->assertSame('done', $run->status);
        $this->assertSame(3, $run->processed_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertSame($b->id, $run->failures[0]['photo_id']);
        $this->assertSame('损坏的训练照片', $run->failures[0]['title']);
        Livewire::test(ManagePhotoDuplicates::class)->mountAction('progress', ['runId' => $run->id])
            ->assertMountedActionModalSee('100%')->assertMountedActionModalSee('成功 2 张')
            ->assertMountedActionModalSee('异常 1 张')->assertMountedActionModalSee('损坏的训练照片')
            ->assertMountedActionModalSee('training-broken.jpg')->assertMountedActionModalSee('查看图片档案');
    }

    public function test_queue_submission_failure_is_reported_in_the_progress_modal(): void
    {
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('队列不可用'));
        Livewire::test(ManagePhotoDuplicates::class)->callAction('groups')
            ->assertActionMounted('progress')->assertMountedActionModalSee('任务提交失败，请检查队列连接后重试');
        $this->assertSame('failed', PhotoGovernanceRun::sole()->status);
    }

    public function test_exhausted_database_lock_is_reported_as_a_save_error_and_later_photos_continue(): void
    {
        Queue::fake();
        $a = Photo::create(['title' => '原图正常但保存繁忙']);
        $b = Photo::create(['title' => '后续正常图片']);
        foreach ([$a, $b] as $photo) {
            PhotoAnalysisResult::create(['photo_id' => $photo->id, 'sha256_hash' => hash('sha256', $photo->title)]);
        }
        $service = Mockery::mock(PhotoSimilarityService::class);
        $service->shouldReceive('generateCandidates')->with(Mockery::on(fn (Photo $photo) => $photo->is($a)))
            ->once()->andThrow(new QueryException('sqlite', 'update secret_table', [], new \PDOException('database is locked')));
        $service->shouldReceive('generateCandidates')->with(Mockery::on(fn (Photo $photo) => $photo->is($b)))->once()->andReturn(0);
        $run = PhotoGovernanceRun::create(['type' => 'duplicates', 'operated_by' => auth()->id()]);
        (new ProcessPhotoGovernance($run->id))->handle(app(PhotoSimilarityGovernance::class), $service);
        $run->refresh();
        $this->assertSame('done', $run->status);
        $this->assertSame(2, $run->processed_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertStringContainsString('保存匹配结果失败：数据库暂时繁忙', $run->failures[0]['reason']);
        $this->assertStringNotContainsString('secret_table', $run->failures[0]['reason']);
        Livewire::test(ManagePhotoDuplicates::class)->mountAction('progress', ['runId' => $run->id])
            ->assertMountedActionModalSee('原图无需修改')->assertMountedActionModalSee('100%');
    }

    public function test_image_failures_across_batches_advance_once_and_do_not_repeat_on_redelivery(): void
    {
        Queue::fake();
        $photos = collect();
        for ($i = 0; $i < 11; $i++) {
            $photo = Photo::create(['title' => '检查照片 '.$i]);
            PhotoAnalysisResult::create(['photo_id' => $photo->id, 'sha256_hash' => hash('sha256', (string) $i)]);
            $photos->push($photo);
        }
        $badIds = [$photos[2]->id, $photos[10]->id];
        $service = Mockery::mock(PhotoSimilarityService::class);
        $service->shouldReceive('generateCandidates')->times(11)->andReturnUsing(function (Photo $photo) use ($badIds): int {
            if (in_array($photo->id, $badIds, true)) {
                throw new \RuntimeException('原图文件不存在：test.jpg');
            }

            return 0;
        });
        $run = PhotoGovernanceRun::create(['type' => 'duplicates', 'operated_by' => auth()->id()]);
        $job = new ProcessPhotoGovernance($run->id);
        $job->handle(app(PhotoSimilarityGovernance::class), $service);
        $this->assertSame('running', $run->fresh()->status);
        $this->assertSame(10, $run->fresh()->processed_count);
        $this->assertSame(1, $run->fresh()->failed_count);
        $job->handle(app(PhotoSimilarityGovernance::class), $service);
        $job->handle(app(PhotoSimilarityGovernance::class), $service);
        $job->failed(new \RuntimeException('过期的任务回调'));
        $this->assertSame('done', $run->fresh()->status);
        $this->assertSame(11, $run->fresh()->processed_count);
        $this->assertSame(2, $run->fresh()->failed_count);
        $this->assertCount(2, $run->fresh()->failures);
        $this->assertSame(9, $run->fresh()->successfulCount());
        Queue::assertPushed(ProcessPhotoGovernance::class, 1);
    }

    public function test_missing_source_is_recorded_by_title_and_task_level_failure_still_interrupts(): void
    {
        Queue::fake();
        $photo = Photo::create(['title' => '测试图123']);
        $run = PhotoGovernanceRun::create(['type' => 'duplicates', 'operated_by' => auth()->id()]);
        (new ProcessPhotoGovernance($run->id))->handle(app(PhotoSimilarityGovernance::class), app(PhotoSimilarityService::class));
        $run->refresh();
        $this->assertSame('done', $run->status);
        $this->assertSame(1, $run->failed_count);
        $this->assertSame(0, $run->successfulCount());
        Livewire::test(ManagePhotoDuplicates::class)->mountAction('progress', ['runId' => $run->id])
            ->assertMountedActionModalSee('测试图123')->assertMountedActionModalSee('没有关联原图文件')
            ->assertMountedActionModalSeeHtml('tableAction=edit')
            ->assertMountedActionModalSeeHtml('tableActionRecord='.$photo->id);

        $interrupted = PhotoGovernanceRun::create(['type' => 'duplicates', 'status' => 'running', 'total_count' => 2, 'processed_count' => 1]);
        (new ProcessPhotoGovernance($interrupted->id))->failed(new \RuntimeException('系统级故障'));
        Livewire::test(ManagePhotoDuplicates::class)->mountAction('progress', ['runId' => $interrupted->id])
            ->assertMountedActionModalSee('本次任务未完成')->assertMountedActionModalSee('任务执行中断');
    }
}
