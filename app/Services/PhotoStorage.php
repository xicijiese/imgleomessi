<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PhotoStorage
{
    public function __construct(private readonly StorageSettings $settings) {}

    public function activeDiskName(): string
    {
        return $this->settings->activeMode() === 'cos' ? 'cos' : 'public';
    }

    public function diskForKey(?string $key = null): FilesystemAdapter
    {
        $local = Storage::disk('public');

        if ($this->activeDiskName() === 'public' || (filled($key) && $local->exists($key))) {
            return $local;
        }

        return $this->cosDisk();
    }

    public function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = ltrim((string) $path, '/');
        $local = Storage::disk('public');

        if ($this->activeDiskName() === 'public' || $local->exists($path)) {
            return '/storage/'.$path;
        }

        $credentials = $this->settings->credentials();
        $baseUrl = trim($credentials['cdn_url'], '/');

        if ($baseUrl !== '') {
            return $baseUrl.'/'.$path;
        }

        return sprintf(
            'https://%s.cos.%s.myqcloud.com/%s',
            $credentials['bucket'],
            $credentials['region'],
            $path,
        );
    }

    public function healthCheck(): void
    {
        $disk = $this->diskForKey();
        $key = 'system/health-check/'.Str::uuid().'.txt';

        if (! $disk->put($key, 'ok') || $disk->get($key) !== 'ok') {
            throw new RuntimeException('当前存储方式无法完成读写检查。');
        }

        $disk->delete($key);
    }

    public function cosObjectExists(?string $key): bool
    {
        if (blank($key)) {
            return false;
        }

        return $this->cosDisk()->exists($key);
    }

    private function cosDisk(): FilesystemAdapter
    {
        $credentials = $this->settings->credentials();

        foreach (['secret_id', 'secret_key', 'region', 'bucket'] as $key) {
            if (blank($credentials[$key])) {
                throw new RuntimeException('腾讯云存储配置不完整，无法创建 COS 存储连接。');
            }
        }

        return Storage::build([
            'driver' => 's3',
            'key' => $credentials['secret_id'],
            'secret' => $credentials['secret_key'],
            'region' => $credentials['region'],
            'bucket' => $credentials['bucket'],
            'endpoint' => 'https://cos.'.$credentials['region'].'.myqcloud.com',
            'use_path_style_endpoint' => false,
            'throw' => true,
            'report' => false,
        ]);
    }
}
