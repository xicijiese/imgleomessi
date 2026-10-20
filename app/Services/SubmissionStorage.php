<?php

namespace App\Services;

use App\Models\Photo;
use App\Models\PhotoSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Qcloud\Cos\Client;
use Qcloud\Cos\Exception\ServiceResponseException;
use RuntimeException;

class SubmissionStorage
{
    public function __construct(private readonly StorageSettings $settings, private readonly ?Client $cos = null) {}

    public function location(): array
    {
        $credentials = $this->settings->credentials();

        return $this->settings->activeMode() === 'cos'
            ? ['driver' => 'cos', 'location' => ['bucket' => ($credentials['submission_bucket'] ?? '') ?: $credentials['bucket'], 'region' => $credentials['region']]]
            : ['driver' => 'local', 'location' => null];
    }

    public function libraryLocation(): array
    {
        $credentials = $this->settings->credentials();

        return $this->settings->activeMode() === 'cos'
            ? ['driver' => 'cos', 'location' => ['bucket' => $credentials['bucket'], 'region' => $credentials['region']]]
            : ['driver' => 'local', 'location' => null];
    }

    public function put(PhotoSubmission $submission, UploadedFile $file): void
    {
        $this->guardKey($submission);
        if ($submission->storage_driver === 'local') {
            if (! Storage::disk('local')->put($submission->temporary_key, file_get_contents($file->getRealPath()))) {
                throw new RuntimeException('投稿暂存写入失败。');
            }

            return;
        }
        $result = $this->client($submission)->putObject([
            'Bucket' => $submission->storage_location['bucket'], 'Key' => $submission->temporary_key,
            'Body' => 'private-access-probe', 'ACL' => 'private', 'ContentType' => 'application/octet-stream',
        ]);
        $submission->update(['version_id' => $result['VersionId'] ?? null]);
        // 私有 ACL 可能被存储桶公开策略覆盖，必须实测匿名访问，不能仅凭目录名判断私有。
        $url = 'https://'.$submission->storage_location['bucket'].'.cos.'.$submission->storage_location['region'].'.myqcloud.com/'.$submission->temporary_key;
        $response = Http::timeout(15)->withHeaders(['Range' => 'bytes=0-0'])->get($url);
        if ($response->status() !== 403) {
            throw new RuntimeException('COS 投稿区域未通过私有访问检查，请检查存储桶公开读取策略。');
        }
        $result = $this->client($submission)->putObject([
            'Bucket' => $submission->storage_location['bucket'], 'Key' => $submission->temporary_key,
            'Body' => file_get_contents($file->getRealPath()), 'ACL' => 'private', 'ContentType' => $submission->mime_type,
        ]);
        $submission->update(['version_id' => $result['VersionId'] ?? null]);
    }

    public function contents(PhotoSubmission $submission): string
    {
        $this->guardKey($submission);
        if ($submission->storage_driver === 'local') {
            return Storage::disk('local')->get($submission->temporary_key);
        }
        $args = ['Bucket' => $submission->storage_location['bucket'], 'Key' => $submission->temporary_key];
        if ($submission->version_id) {
            $args['VersionId'] = $submission->version_id;
        }

        return (string) $this->client($submission)->getObject($args)['Body'];
    }

    public function delete(PhotoSubmission $submission): void
    {
        $this->guardKey($submission);
        if ($submission->storage_driver === 'local') {
            $disk = Storage::disk('local');
            if ($disk->exists($submission->temporary_key) && ! $disk->delete($submission->temporary_key)) {
                throw new RuntimeException('暂存文件删除失败。');
            }

            return;
        }
        $client = $this->client($submission);
        $args = ['Bucket' => $submission->storage_location['bucket'], 'Key' => $submission->temporary_key];
        $this->purgeCos($client, $args);
    }

    public function discardUnadoptedAssets(PhotoSubmission $row): void
    {
        if (! $row->import_location || $row->status === 'accepted') {
            return;
        }
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$row->mime_type];
        $keys = ['photos/originals/submissions/'.$row->asset_uuid.'.'.$extension,
            'photos/derived/'.$row->asset_uuid.'/display.webp', 'photos/derived/'.$row->asset_uuid.'/thumbnail.webp'];
        if (Photo::query()->whereIn('original_key', $keys)->orWhereIn('display_key', $keys)->orWhereIn('thumbnail_key', $keys)->exists()) {
            return;
        }
        if ($row->import_location['driver'] === 'local') {
            $disk = Storage::disk('public');
            foreach ($keys as $key) {
                if ($disk->exists($key) && ! $disk->delete($key)) {
                    throw new RuntimeException('未入库文件清理失败。');
                }
            }
        } else {
            $copy = clone $row;
            $copy->storage_location = $row->import_location['location'];
            $client = $this->client($copy);
            foreach ($keys as $key) {
                $this->purgeCos($client, ['Bucket' => $copy->storage_location['bucket'], 'Key' => $key]);
            }
        }
    }

    private function purgeCos(Client $client, array $args): void
    {
        $versioning = $client->getBucketVersioning(['Bucket' => $args['Bucket']]);
        if (in_array($versioning['Status'] ?? '', ['Enabled', 'Suspended'], true)) {
            // 独占随机 Key，只清理该投稿对象的版本，不触碰其他图片。
            do {
                $result = $client->listObjectVersions(['Bucket' => $args['Bucket'], 'Prefix' => $args['Key']]);
                $versions = array_merge($result['Version'] ?? [], $result['DeleteMarkers'] ?? []);
                $matching = array_filter($versions, fn ($version) => ($version['Key'] ?? null) === $args['Key']);
                foreach ($matching as $version) {
                    $client->deleteObject([...$args, 'VersionId' => $version['VersionId']]);
                }
            } while ($matching !== []);
        } else {
            $client->deleteObject($args);
            try {
                $client->headObject($args);
            } catch (ServiceResponseException $error) {
                if ($error->getStatusCode() === 404) {
                    return;
                }
                throw $error;
            }
            throw new RuntimeException('COS 文件删除后仍可读取，保留待清理状态。');
        }
    }

    private function guardKey(PhotoSubmission $submission): void
    {
        if ($submission->temporary_key !== 'submissions/private/'.$submission->id.'/original') {
            throw new RuntimeException('拒绝访问不属于该投稿的文件。');
        }
    }

    private function client(PhotoSubmission $submission): Client
    {
        $credentials = $this->settings->credentials();

        return $this->cos ?? new Client(['region' => $submission->storage_location['region'], 'schema' => 'https', 'timeout' => 30,
            'credentials' => ['secretId' => $credentials['secret_id'], 'secretKey' => $credentials['secret_key']]]);
    }
}
