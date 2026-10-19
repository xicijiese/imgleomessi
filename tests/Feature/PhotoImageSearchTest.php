<?php

namespace Tests\Feature;

use App\Jobs\ProcessPhotoImageSearch;
use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoImageSearch;
use App\Services\PhotoSimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhotoImageSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_private_query_and_receive_only_public_matches(): void
    {
        Queue::fake();
        Storage::fake('local');
        $file = UploadedFile::fake()->image('query.jpg', 40, 30);
        $hash = app(PhotoSimilarityService::class)->hashForContents(file_get_contents($file->getRealPath()));
        foreach (['published', 'draft', 'archived'] as $status) {
            $photo = Photo::query()->create(['title' => $status, 'status' => $status, 'original_key' => 'secret.jpg']);
            PhotoAnalysisResult::query()->create(['photo_id' => $photo->id, 'perceptual_hash' => $hash]);
        }
        $restricted = Photo::query()->create(['title' => 'restricted', 'status' => 'published', 'copyright_status' => 'restricted']);
        PhotoAnalysisResult::query()->create(['photo_id' => $restricted->id, 'perceptual_hash' => $hash]);
        $this->get('/search/image')->assertOk();
        $response = $this->post('/search/image', ['image' => $file]);
        $query = PhotoImageSearch::query()->firstOrFail();
        $response->assertRedirect('/search/image/'.$query->id);
        Queue::assertPushed(ProcessPhotoImageSearch::class, fn ($job) => $job->connection === 'database');
        $path = $query->temporary_path;
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseCount('photos', 4);
        (new ProcessPhotoImageSearch($query->id))->handle(app(PhotoSimilarityService::class));
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($query->fresh()->temporary_path);
        $this->get('/search/image/'.$query->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Search/Image')->where('imageSearch.status', 'done')->has('photos', 1)
            ->where('photos.0.title', 'published')->missing('photos.0.original_key')->missing('imageSearch.session_hash'));
        Photo::query()->where('title', 'published')->update(['status' => 'archived']);
        $this->get('/search/image/'.$query->id)->assertInertia(fn (Assert $page) => $page->has('photos', 0));
        $this->flushSession();
        $this->get('/search/image/'.$query->id)->assertNotFound();
    }

    public function test_invalid_and_oversized_files_are_rejected(): void
    {
        Queue::fake();
        $this->post('/search/image', ['image' => 'invalid-input'])->assertSessionHasErrors('image');
        $this->post('/search/image', ['image' => UploadedFile::fake()->create('not-image.txt', 1, 'text/plain')])->assertSessionHasErrors('image');
        $this->post('/search/image', ['image' => UploadedFile::fake()->image('large.jpg')->size(5121)])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('photo_image_searches', 0);
        Queue::assertNothingPushed();
    }

    public function test_failed_and_expired_queries_do_not_keep_temporary_files(): void
    {
        Storage::fake('local');
        $query = PhotoImageSearch::query()->create(['id' => (string) Str::uuid(), 'session_hash' => 'test', 'temporary_path' => 'image-search/query.jpg', 'expires_at' => now()->addHour()]);
        Storage::disk('local')->put($query->temporary_path, 'broken');
        (new ProcessPhotoImageSearch($query->id))->handle(app(PhotoSimilarityService::class));
        $this->assertSame('failed', $query->fresh()->status);
        Storage::disk('local')->assertMissing('image-search/query.jpg');
        $query->update(['status' => 'pending', 'temporary_path' => 'image-search/expired.jpg', 'expires_at' => now()->subSecond()]);
        Storage::disk('local')->put($query->temporary_path, 'broken');
        (new ProcessPhotoImageSearch($query->id))->handle(app(PhotoSimilarityService::class));
        Storage::disk('local')->assertMissing('image-search/expired.jpg');
        $this->get('/search/image/'.$query->id)->assertNotFound();
    }

    public function test_query_submission_is_rate_limited(): void
    {
        Queue::fake();
        Storage::fake('local');
        for ($i = 0; $i < 6; $i++) {
            $this->post('/search/image', ['image' => UploadedFile::fake()->image('query.jpg')])->assertRedirect();
        }
        $this->post('/search/image', ['image' => UploadedFile::fake()->image('query.jpg')])->assertStatus(429);
    }

    public function test_expired_pending_queries_are_cleaned_by_scheduler(): void
    {
        Storage::fake('local');
        $this->travelTo(now()->startOfHour());
        $expired = PhotoImageSearch::query()->create(['id' => (string) Str::uuid(), 'session_hash' => 'test', 'temporary_path' => 'image-search/expired.jpg', 'expires_at' => now()->subMinute()]);
        Storage::disk('local')->put($expired->temporary_path, 'test');
        $this->artisan('schedule:run')->assertExitCode(0);
        Storage::disk('local')->assertMissing('image-search/expired.jpg');
        $this->assertDatabaseMissing('photo_image_searches', ['id' => $expired->id]);
    }
}
