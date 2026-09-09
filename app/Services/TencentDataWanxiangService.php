<?php

namespace App\Services;

use App\Models\Photo;
use Qcloud\Cos\Client;
use Qcloud\Cos\ImageParamTemplate\ImageMogrTemplate;
use Qcloud\Cos\ImageParamTemplate\PicOperationsTransformation;
use RuntimeException;

class TencentDataWanxiangService
{
    public function __construct(
        private readonly StorageSettings $settings,
        private readonly ?Client $client = null,
    ) {}

    public function enabled(): bool
    {
        $credentials = $this->settings->credentials();

        return $this->settings->activeMode() === 'cos'
            && $credentials['datawanxiang_enabled']
            && filled($credentials['secret_id'])
            && filled($credentials['secret_key'])
            && filled($credentials['region'])
            && filled($credentials['bucket']);
    }

    /**
     * @return array{display_key: string, thumbnail_key: string}
     */
    public function generateDerivatives(Photo $photo): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('腾讯云数据万象未启用或 COS 配置不完整。');
        }

        if (blank($photo->original_key)) {
            throw new RuntimeException('图片缺少原图存储 Key。');
        }

        $displayKey = 'photos/derived/'.$photo->uuid.'/display.webp';
        $thumbnailKey = 'photos/derived/'.$photo->uuid.'/thumbnail.webp';
        $operations = new PicOperationsTransformation();
        $operations->setIsPicInfo(1);
        $operations->addRule($this->imageRule(2048, 2048), '/'.$displayKey);
        $operations->addRule($this->imageRule(600, 600), '/'.$thumbnailKey);

        $result = $this->client()->ImageProcess([
            'Bucket' => $this->settings->credentials()['bucket'],
            'Key' => $photo->original_key,
            'PicOperations' => $operations->queryString(),
        ]);

        $this->assertProcessedKeys($result, [$displayKey, $thumbnailKey]);

        return [
            'display_key' => $displayKey,
            'thumbnail_key' => $thumbnailKey,
        ];
    }

    public function recognizeText(Photo $photo): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('腾讯云数据万象未启用或 COS 配置不完整。');
        }

        if (blank($photo->original_key)) {
            throw new RuntimeException('图片缺少原图存储 Key。');
        }

        $result = $this->client()->opticalOcrRecognition([
            'Bucket' => $this->settings->credentials()['bucket'],
            'Key' => $photo->original_key,
            'Type' => 'general',
            'LanguageType' => 'zh',
        ]);

        $data = is_array($result)
            ? $result
            : (is_object($result) && method_exists($result, 'toArray') ? $result->toArray() : []);
        $data = is_array($data) ? ($data['Response'] ?? ($data['Data'] ?? $data)) : [];
        $detections = is_array($data) ? ($data['TextDetections'] ?? []) : [];

        if (is_array($detections) && array_key_exists('DetectedText', $detections)) {
            $detections = [$detections];
        }

        return collect(is_array($detections) ? $detections : [])
            ->map(fn (mixed $detection): ?string => data_get($detection, 'DetectedText'))
            ->filter(fn (?string $text): bool => filled($text))
            ->map(fn (string $text): string => trim($text))
            ->filter()
            ->implode("\n");
    }
    private function imageRule(int $width, int $height): ImageMogrTemplate
    {
        $rule = new ImageMogrTemplate();
        $rule->thumbnailByMaxWH($width, $height);
        $rule->format('webp');

        return $rule;
    }

    private function client(): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        $credentials = $this->settings->credentials();

        return new Client([
            'region' => $credentials['region'],
            'schema' => 'https',
            'timeout' => 30,
            'credentials' => [
                'secretId' => $credentials['secret_id'],
                'secretKey' => $credentials['secret_key'],
            ],
        ]);
    }

    /**
     * @param  mixed  $result
     * @param  array<int, string>  $expectedKeys
     */
    private function assertProcessedKeys(mixed $result, array $expectedKeys): void
    {
        $data = is_array($result)
            ? $result
            : (is_object($result) && method_exists($result, 'toArray') ? $result->toArray() : null);
        $data = is_array($data) ? ($data['Data'] ?? $data) : null;
        $objects = is_array($data) ? data_get($data, 'ProcessResults.Object', []) : [];

        if (is_array($objects) && array_key_exists('Key', $objects)) {
            $objects = [$objects];
        }

        $processedKeys = collect(is_array($objects) ? $objects : [])
            ->map(fn (mixed $object): mixed => is_array($object) ? ($object['Key'] ?? null) : null)
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();

        foreach ($expectedKeys as $expectedKey) {
            if (! in_array($expectedKey, $processedKeys, true)) {
                throw new RuntimeException('腾讯云数据万象未返回预期的处理结果。');
            }
        }
    }
}