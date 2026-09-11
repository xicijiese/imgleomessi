<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\PhotoStorage;
use App\Services\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_settings_default_to_local_mode(): void
    {
        $state = app(StorageSettings::class)->formState();

        $this->assertSame('local', $state['mode']);
        $this->assertFalse($state['cos']['secret_key_saved']);
        $this->assertSame('', $state['cos']['secret_key']);
    }

    public function test_cos_credentials_are_encrypted_and_secret_is_not_returned_to_form(): void
    {
        $user = User::factory()->create();

        app(StorageSettings::class)->save([
            'mode' => 'cos',
            'cos' => [
                'secret_id' => 'AKID-example',
                'secret_key' => 'SECRET-example',
                'region' => 'ap-guangzhou',
                'bucket' => 'messi-1250000000',
                'cdn_url' => 'https://cdn.example.com',
                'datawanxiang_enabled' => true,
            ],
        ], $user);

        $stored = Setting::query()
            ->where('group', 'storage')
            ->where('key', 'config')
            ->firstOrFail();

        $this->assertNotSame('SECRET-example', $stored->value['cos']['secret_key']);
        $this->assertSame('SECRET-example', Crypt::decryptString($stored->value['cos']['secret_key']));
        $this->assertSame('', app(StorageSettings::class)->formState()['cos']['secret_key']);
        $this->assertTrue(app(StorageSettings::class)->formState()['cos']['secret_key_saved']);
        $this->assertSame('cos', app(StorageSettings::class)->activeMode());
    }

    public function test_cloud_mode_uses_configured_cdn_for_public_media_urls(): void
    {
        app(StorageSettings::class)->save([
            'mode' => 'cos',
            'cos' => [
                'secret_id' => 'AKID-example',
                'secret_key' => 'SECRET-example',
                'region' => 'ap-guangzhou',
                'bucket' => 'messi-1250000000',
                'cdn_url' => 'https://cdn.example.com/',
            ],
        ]);

        $this->assertSame(
            'https://cdn.example.com/photos/display/example.webp',
            app(PhotoStorage::class)->url('photos/display/example.webp'),
        );
    }

    public function test_blank_secret_keeps_existing_cos_secret(): void
    {
        $service = app(StorageSettings::class);
        $user = User::factory()->create();

        $service->save([
            'mode' => 'cos',
            'cos' => [
                'secret_id' => 'AKID-example',
                'secret_key' => 'SECRET-example',
                'region' => 'ap-guangzhou',
                'bucket' => 'messi-1250000000',
            ],
        ], $user);

        $service->save([
            'mode' => 'cos',
            'cos' => [
                'secret_id' => 'AKID-updated',
                'secret_key' => '',
                'region' => 'ap-shanghai',
                'bucket' => 'messi-1250000000',
            ],
        ], $user);

        $this->assertSame('SECRET-example', $service->credentials()['secret_key']);
        $this->assertSame('AKID-updated', $service->credentials()['secret_id']);
    }

    public function test_local_mode_keeps_cos_urls_for_cos_only_media(): void
    {
        app(StorageSettings::class)->save([
            'mode' => 'local',
            'cos' => [
                'secret_id' => 'AKID-example',
                'secret_key' => 'SECRET-example',
                'region' => 'ap-guangzhou',
                'bucket' => 'messi-1250000000',
                'cdn_url' => 'https://cdn.example.com/',
            ],
        ]);

        $this->assertSame(
            'https://cdn.example.com/photos/derived/cos-only/display.webp',
            app(PhotoStorage::class)->url('photos/derived/cos-only/display.webp'),
        );
    }

    public function test_local_mode_uses_cos_disk_for_media_not_present_locally(): void
    {
        app(StorageSettings::class)->save([
            'mode' => 'local',
            'cos' => [
                'secret_id' => 'AKID-example',
                'secret_key' => 'SECRET-example',
                'region' => 'ap-guangzhou',
                'bucket' => 'messi-1250000000',
            ],
        ]);

        $localDisk = Storage::fake('public-fallback');
        $cloudDisk = Storage::fake('cloud-fallback');

        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturn($localDisk);

        Storage::shouldReceive('build')
            ->once()
            ->andReturn($cloudDisk);

        $this->assertSame(
            $cloudDisk,
            app(PhotoStorage::class)->diskForKey('photos/derived/cos-only/display.webp'),
        );
    }

    public function test_cos_object_exists_checks_the_configured_cloud_disk(): void
    {
        app(StorageSettings::class)->save([
            'mode' => 'cos',
            'cos' => [
                'secret_id' => 'AKID-example',
                'secret_key' => 'SECRET-example',
                'region' => 'ap-guangzhou',
                'bucket' => 'messi-1250000000',
            ],
        ]);

        $cloudDisk = Storage::fake('cloud-check');
        $cloudDisk->put('photos/originals/cloud.jpg', 'image');

        Storage::shouldReceive('build')
            ->twice()
            ->andReturn($cloudDisk);

        $storage = app(PhotoStorage::class);

        $this->assertTrue($storage->cosObjectExists('photos/originals/cloud.jpg'));
        $this->assertFalse($storage->cosObjectExists('photos/originals/local-only.jpg'));
    }}
