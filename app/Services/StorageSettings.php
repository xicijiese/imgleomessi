<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Throwable;

class StorageSettings
{
    private const GROUP = 'storage';

    private const KEY = 'config';

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'mode' => 'local',
            'cos' => [
                'secret_id' => '',
                'secret_key' => '',
                'secret_key_saved' => false,
                'region' => '',
                'bucket' => '',
                'cdn_url' => '',
                'datawanxiang_enabled' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formState(): array
    {
        $saved = Setting::value(self::GROUP, self::KEY, []);
        $state = array_replace_recursive($this->defaults(), is_array($saved) ? $saved : []);
        $encryptedSecret = Arr::get($state, 'cos.secret_key');

        $state['cos']['secret_key'] = '';
        $state['cos']['secret_key_saved'] = filled($encryptedSecret);

        return $state;
    }

    public function activeMode(): string
    {
        return Arr::get($this->formState(), 'mode') === 'cos'
            ? 'cos'
            : 'local';
    }

    /**
     * @return array{secret_id: string, secret_key: string, region: string, bucket: string, cdn_url: string, datawanxiang_enabled: bool}
     */
    public function credentials(): array
    {
        $state = Setting::value(self::GROUP, self::KEY, []);
        $cos = is_array($state) ? Arr::get($state, 'cos', []) : [];
        $encryptedSecret = is_string($cos['secret_key'] ?? null) ? $cos['secret_key'] : '';

        return [
            'secret_id' => (string) ($cos['secret_id'] ?? ''),
            'secret_key' => $this->decryptSecret($encryptedSecret),
            'region' => (string) ($cos['region'] ?? ''),
            'bucket' => (string) ($cos['bucket'] ?? ''),
            'cdn_url' => (string) ($cos['cdn_url'] ?? ''),
            'datawanxiang_enabled' => (bool) ($cos['datawanxiang_enabled'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function save(array $state, ?User $user = null): Setting
    {
        $mode = Arr::get($state, 'mode', 'local');
        $cos = Arr::get($state, 'cos', []);
        $cos = is_array($cos) ? $cos : [];
        $existing = Setting::value(self::GROUP, self::KEY, []);
        $existingCos = is_array($existing) ? Arr::get($existing, 'cos', []) : [];
        $secretKey = filled($cos['secret_key'] ?? null)
            ? (string) $cos['secret_key']
            : $this->decryptSecret((string) ($existingCos['secret_key'] ?? ''));

        if (! in_array($mode, ['local', 'cos'], true)) {
            throw ValidationException::withMessages(['storage.mode' => '存储方式不正确。']);
        }

        if ($mode === 'cos') {
            $missing = collect([
                'secret_id' => '腾讯云 SecretId',
                'secret_key' => '腾讯云 SecretKey',
                'region' => 'COS 地域',
                'bucket' => 'COS 存储桶',
            ])->filter(function (string $label, string $key) use ($cos, $secretKey): bool {
                $value = $key === 'secret_key' ? $secretKey : ($cos[$key] ?? null);

                return blank($value);
            })
                ->mapWithKeys(fn (string $label, string $key): array => ['storage.cos.'.$key => $label.'不能为空。'])->all();

            if ($missing !== []) {
                throw ValidationException::withMessages($missing);
            }
        }

        return Setting::setValue(self::GROUP, self::KEY, [
            'mode' => $mode,
            'cos' => [
                'secret_id' => trim((string) ($cos['secret_id'] ?? '')),
                'secret_key' => filled($secretKey) ? Crypt::encryptString($secretKey) : '',
                'region' => trim((string) ($cos['region'] ?? '')),
                'bucket' => trim((string) ($cos['bucket'] ?? '')),
                'cdn_url' => trim((string) ($cos['cdn_url'] ?? '')),
                'datawanxiang_enabled' => (bool) ($cos['datawanxiang_enabled'] ?? false),
            ],
        ], $user, '项目图片存储方式与腾讯云数据万象配置');
    }

    private function decryptSecret(string $value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return '';
        }
    }
}
