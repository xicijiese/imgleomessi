<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoSimilarityCandidate;
use App\Services\PhotoSimilarityService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PhotoSimilarityRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 必须使用顶层事务，才能验证锁冲突后的真实回滚与重试。
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    public function test_temporary_database_lock_is_retried_without_duplicate_pairs(): void
    {
        $photos = $this->photos();
        $attempts = 0;
        PhotoSimilarityCandidate::saving(function () use (&$attempts) {
            if (++$attempts <= 2) {
                throw $this->locked();
            }
        });
        try {
            $count = app(PhotoSimilarityService::class)->generateCandidates($photos[0]);
            $this->assertSame(2, $count);
            $this->assertDatabaseCount('photo_similarity_candidates', 2);
            $this->assertSame(4, $attempts);
        } finally {
            Event::forget('eloquent.saving: '.PhotoSimilarityCandidate::class);
        }
    }

    public function test_persistent_lock_stops_after_three_attempts_and_rolls_back_partial_results(): void
    {
        $photos = $this->photos();
        $attempts = 0;
        PhotoSimilarityCandidate::saving(function () use (&$attempts) {
            $attempts++;
            if ($attempts % 2 === 0) {
                throw $this->locked();
            }
        });
        try {
            app(PhotoSimilarityService::class)->generateCandidates($photos[0]);
            $this->fail('持续锁冲突应在有限重试后返回失败');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('database is locked', $exception->getMessage());
            $this->assertSame(6, $attempts);
            $this->assertDatabaseCount('photo_similarity_candidates', 0);
        } finally {
            Event::forget('eloquent.saving: '.PhotoSimilarityCandidate::class);
        }
    }

    private function photos(): array
    {
        $photos = [];
        foreach (['甲', '乙', '丙'] as $title) {
            $photo = Photo::create(['title' => $title]);
            PhotoAnalysisResult::create(['photo_id' => $photo->id, 'perceptual_hash' => '0000000000000000']);
            $photos[] = $photo;
        }

        return $photos;
    }

    private function locked(): QueryException
    {
        return new QueryException('sqlite', 'insert into photo_similarity_candidates', [], new \PDOException('database is locked'));
    }
}
