<?php

namespace App\Services;

use App\Models\Photo;
use Throwable;

class StorageLaunchCheck
{
    public function __construct(
        private readonly StorageSettings $settings,
        private readonly PhotoStorage $storage,
    ) {}

    /**
     * @return array{passed: bool, warnings: array<int, string>, failures: array<int, string>, checks: array<int, string>}
     */
    public function run(): array
    {
        $warnings = [];
        $failures = [];
        $checks = [];
        $mode = $this->settings->activeMode();

        $checks[] = $mode === 'cos' ? '存储模式：腾讯云 COS' : '存储模式：项目运行环境本地存储';

        try {
            $this->storage->healthCheck();
            $checks[] = '存储读写：通过';
        } catch (Throwable $throwable) {
            $failures[] = '存储读写失败：'.$throwable->getMessage();
        }

        if ($mode === 'local') {
            return [
                'passed' => $failures === [],
                'warnings' => $warnings,
                'failures' => $failures,
                'checks' => $checks,
            ];
        }

        $credentials = $this->settings->credentials();
        foreach (['secret_id' => 'SecretId', 'secret_key' => 'SecretKey', 'region' => 'COS 地域', 'bucket' => 'COS 存储桶'] as $key => $label) {
            if (blank($credentials[$key])) {
                $failures[] = $label.'未配置。';
            }
        }

        if (blank($credentials['cdn_url'])) {
            $warnings[] = '未配置 CDN 域名，展示地址将直接使用 COS 地址。';
        } elseif (filter_var($credentials['cdn_url'], FILTER_VALIDATE_URL) === false) {
            $failures[] = 'CDN 域名不是有效的 URL。';
        } else {
            $checks[] = 'CDN 地址格式：通过';
        }

        $photo = Photo::query()
            ->whereNotNull('original_key')
            ->latest('updated_at')
            ->first();

        if (! $photo instanceof Photo) {
            $warnings[] = '当前没有可用于验证原图、展示图和缩略图的图片。';
        } else {
            foreach ([
                'original_key' => '原图',
                'display_key' => '展示图',
                'thumbnail_key' => '缩略图',
            ] as $attribute => $label) {
                $key = $photo->getAttribute($attribute);

                if (blank($key)) {
                    $failures[] = '代表性图片的'.$label.' Key 未生成。';
                    continue;
                }

                try {
                    if (! $this->storage->cosObjectExists($key)) {
                        $failures[] = '代表性图片的'.$label.'不存在于当前 COS 存储桶。';
                        continue;
                    }

                    $checks[] = $label.'对象：通过';
                } catch (Throwable $throwable) {
                    $failures[] = $label.'检查失败：'.$throwable->getMessage();
                }
            }
        }

        if (! (bool) ($this->settings->credentials()['datawanxiang_enabled'] ?? false)) {
            $warnings[] = '数据万象处理开关未开启；不会自动生成展示图、缩略图或识别结果。';
        }

        return [
            'passed' => $failures === [],
            'warnings' => $warnings,
            'failures' => $failures,
            'checks' => $checks,
        ];
    }
}