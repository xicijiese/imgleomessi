<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Services\PhotoStorage;
use App\Services\PublicMediaUrl;
use App\Services\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CosAvifStylesTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_cloud_derivatives_use_the_correct_styles_without_changing_storage_keys(): void
    {
        Storage::fake('public');
        app(StorageSettings::class)->save(['mode' => 'cos', 'cos' => [
            'secret_id' => 'test-id', 'secret_key' => 'test-secret', 'region' => 'ap-guangzhou',
            'bucket' => 'test-1250000000', 'cdn_url' => 'https://cdn.example.com',
        ]]);
        $photo = Photo::create(['title' => '旧照片', 'original_key' => 'originals/private.jpg',
            'thumbnail_key' => 'photos/old/thumb.webp', 'display_key' => 'photos/old/display.webp']);
        $this->assertSame('https://cdn.example.com/photos/old/thumb.webp/gallery_thumb_avif', PublicMediaUrl::thumbnail($photo));
        $this->assertSame('https://cdn.example.com/photos/old/display.webp/gallery_display_avif', PublicMediaUrl::display($photo));
        $this->assertSame('photos/old/display.webp', $photo->fresh()->display_key);
        $this->assertSame('https://cdn.example.com/photos/old/display.webp', app(PhotoStorage::class)->url($photo->display_key));
        config(['media.cos_avif_enabled' => false]);
        $this->assertSame('https://cdn.example.com/photos/old/thumb.webp', PublicMediaUrl::thumbnail($photo));
        config(['media.cos_avif_enabled' => true]);
        Storage::disk('public')->put($photo->thumbnail_key, 'local-fixture');
        $this->assertSame('/storage/photos/old/thumb.webp', PublicMediaUrl::thumbnail($photo));
        $photo->thumbnail_key = $photo->original_key;
        $this->assertNull(PublicMediaUrl::thumbnail($photo));
    }

    public function test_external_and_signed_urls_are_not_rewritten(): void
    {
        $photo = new Photo(['original_key' => 'private.jpg', 'thumbnail_key' => 'https://external.example/a.webp?signature=keep']);
        $this->assertSame($photo->thumbnail_key, PublicMediaUrl::thumbnail($photo));
    }
}
