<?php

namespace Tests\Feature;

use App\Jobs\GeneratePublicImageVariants;
use App\Models\Photo;
use App\Models\Setting;
use App\Services\HomepageSettings;
use App\Services\LocalPhotoDerivativeService;
use App\Services\PhotoStorage;
use App\Services\PublicMediaUrl;
use App\Services\TencentDataWanxiangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicImageVariantsTest extends TestCase
{
    use RefreshDatabase;

    private function source(string $key): void
    {
        Storage::disk('public')->put($key, UploadedFile::fake()->image('source.jpg', 1800, 1200)->getContent());
    }

    private function runJob(GeneratePublicImageVariants $job): void
    {
        $job->handle(app(PhotoStorage::class), app(LocalPhotoDerivativeService::class), app(TencentDataWanxiangService::class));
    }

    public function test_photo_lists_use_real_thumbnail_and_detail_uses_display_without_changing_original(): void
    {
        Storage::fake('public');
        $key = 'photos/originals/test.jpg';
        $this->source($key);
        $hash = hash('sha256', Storage::disk('public')->get($key));
        $photo = Photo::query()->create(['title' => '资源分级', 'status' => 'published', 'published_at' => now(), 'original_key' => $key]);
        $this->runJob(new GeneratePublicImageVariants($photo->id));
        $photo->refresh();
        $this->assertSame('published', $photo->status);
        $this->assertSame($hash, hash('sha256', Storage::disk('public')->get($key)));
        $this->assertSame(600, getimagesizefromstring(Storage::disk('public')->get($photo->thumbnail_key))[0]);
        $this->get('/photos')->assertInertia(fn (Assert $page) => $page
            ->where('gallery.photos.data.0.image_url', '/storage/'.$photo->thumbnail_key));
        $this->get('/photos/'.$photo->uuid)->assertInertia(fn (Assert $page) => $page
            ->where('photoDetail.photo.image_url', '/storage/'.$photo->display_key));
    }

    public function test_missing_or_aliased_thumbnail_does_not_fall_back_to_big_image(): void
    {
        Queue::fake();
        $photo = Photo::query()->create(['title' => '缺失缩略图', 'original_key' => 'photos/originals/large.jpg', 'display_key' => 'photos/display/large.webp']);
        $this->assertNull(PublicMediaUrl::thumbnail($photo));
        $photo->thumbnail_key = $photo->original_key;
        $this->assertNull(PublicMediaUrl::thumbnail($photo));
        $photo->thumbnail_key = $photo->display_key;
        $this->assertNull(PublicMediaUrl::thumbnail($photo));
        $this->assertNull(PublicMediaUrl::settingImage('settings/topics/new.jpg'));
        Queue::assertNothingPushed();
    }

    public function test_standalone_setting_images_get_thumbnail_and_display_for_separate_uses(): void
    {
        Storage::fake('public');
        $key = 'settings/topics/test.jpg';
        $this->source($key);
        $this->runJob(new GeneratePublicImageVariants(null, $key));
        $variants = Setting::value('image_variants', hash('sha256', $key));
        $this->assertSame(600, getimagesizefromstring(Storage::disk('public')->get($variants['thumbnail_key']))[0]);
        Setting::setValue('home', 'hero_slides', [['title' => '头图', 'desktop_image_path' => $key]]);
        Setting::setValue('home', 'topic_module', ['enabled' => true, 'items' => [['title' => '专题', 'url' => '/topics/test', 'cover_image_path' => $key]]]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('home.hero_slides.0.desktop_image_url', '/storage/'.$variants['display_key'])
            ->where('home.topic_module.items.0.cover_image_url', '/storage/'.$variants['thumbnail_key']));
        $this->get('/topics')->assertInertia(fn (Assert $page) => $page
            ->where('topicIndex.topics.data.0.cover_image_url', '/storage/'.$variants['thumbnail_key']));
    }

    public function test_repair_is_read_only_by_default_and_apply_only_queues_candidates(): void
    {
        Storage::fake('public');
        Queue::fake();
        $key = 'photos/originals/old.jpg';
        $this->source($key);
        $photo = Photo::query()->create(['title' => '历史占位资源', 'original_key' => $key, 'display_key' => $key, 'thumbnail_key' => $key]);
        $this->artisan('media:repair-variants', ['--local-only' => true])->assertSuccessful();
        Queue::assertNothingPushed();
        $this->artisan('media:repair-variants', ['--local-only' => true, '--apply' => true])->assertSuccessful();
        Queue::assertPushed(GeneratePublicImageVariants::class, fn ($job) => $job->photoId === $photo->id && $job->queue === 'image-variants');
        $this->assertSame($key, $photo->refresh()->thumbnail_key);
    }

    public function test_saving_setting_image_only_queues_processing_and_preserves_upload_path(): void
    {
        Queue::fake();
        $state = HomepageSettings::defaults();
        $state['hero_slides'] = [['desktop_image_path' => 'settings/hero/new.jpg', 'mobile_image_path' => 'settings/hero/new.jpg']];
        $state['topic_module']['items'] = [['title' => '同图专题', 'url' => '/topics/same', 'cover_image_path' => 'settings/hero/new.jpg']];
        app(HomepageSettings::class)->save($state);
        Queue::assertPushed(GeneratePublicImageVariants::class, fn ($job) => $job->source === 'settings/hero/new.jpg');
        Queue::assertPushed(GeneratePublicImageVariants::class, 1);
        $this->assertSame('settings/hero/new.jpg', Setting::value('home', 'hero_slides')[0]['desktop_image_path']);
        $this->assertNull(PublicMediaUrl::settingImage('settings/hero/new.jpg'));
    }

    public function test_cos_sources_use_datawanxiang_without_reading_remote_original_in_the_web_request(): void
    {
        Storage::fake('public');
        $photo = Photo::query()->create(['title' => 'COS资源', 'original_key' => 'photos/originals/cos.jpg']);
        $storage = $this->mock(PhotoStorage::class);
        $storage->shouldReceive('activeDiskName')->once()->andReturn('cos');
        $local = $this->mock(LocalPhotoDerivativeService::class);
        $local->shouldNotReceive('generate');
        $cos = $this->mock(TencentDataWanxiangService::class);
        $cos->shouldReceive('generateDerivatives')->once()->andReturn([
            'display_key' => 'photos/derived/cos/display.webp',
            'thumbnail_key' => 'photos/derived/cos/thumbnail.webp',
        ]);
        (new GeneratePublicImageVariants($photo->id))->handle($storage, $local, $cos);
        $this->assertSame('photos/derived/cos/thumbnail.webp', $photo->refresh()->thumbnail_key);
    }
}
