<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\Setting;
use App\Services\StorageLaunchCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageLaunchCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_storage_launch_check_only_requires_local_read_write(): void
    {
        Storage::fake('public');

        $report = app(StorageLaunchCheck::class)->run();

        $this->assertTrue($report['passed'], json_encode($report, JSON_UNESCAPED_UNICODE));
        $this->assertSame([], $report['failures']);
        $this->assertContains('存储读写：通过', $report['checks']);
    }

    public function test_cos_storage_launch_check_verifies_representative_media_and_cdn(): void
    {
        Setting::setValue('storage', 'config', [
            'mode' => 'cos',
            'cos' => [
                'secret_id' => 'test-secret-id',
                'secret_key' => encrypt('test-secret-key'),
                'region' => 'ap-guangzhou',
                'bucket' => 'testbucket-1250000000',
                'cdn_url' => 'https://cdn.example.com',
                'datawanxiang_enabled' => true,
            ],
        ]);
        $photo = Photo::query()->create([
            'title' => '上线检查样例',
            'original_key' => 'photos/originals/sample.jpg',
            'display_key' => 'photos/derived/sample/display.webp',
            'thumbnail_key' => 'photos/derived/sample/thumbnail.webp',
        ]);

        $publicDisk = Storage::fake('public-launch-check');
        $cloudDisk = Storage::fake('cloud-launch-check');
        $cloudDisk->put($photo->original_key, 'original');
        $cloudDisk->put($photo->display_key, 'display');
        $cloudDisk->put($photo->thumbnail_key, 'thumbnail');
        Storage::shouldReceive('disk')->with('public')->andReturn($publicDisk);
        Storage::shouldReceive('build')->andReturn($cloudDisk);

        $report = app(StorageLaunchCheck::class)->run();

        $this->assertTrue($report['passed'], json_encode($report, JSON_UNESCAPED_UNICODE));
        $this->assertSame([], $report['failures']);
        $this->assertContains('原图对象：通过', $report['checks']);
        $this->assertContains('展示图对象：通过', $report['checks']);
        $this->assertContains('缩略图对象：通过', $report['checks']);
    }
}