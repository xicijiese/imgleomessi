<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class PhotoUploadService
{
    public function __construct(private readonly PhotoStorage $storage) {}

    public function store(
        UploadedFile $file,
        ?Album $album = null,
        ?User $uploader = null,
        ?PhotoUploadBatch $batch = null,
        array $attributes = [],
    ): Photo {
        $storedFilename = $this->makeStoredFilename($file);
        $directory = now()->format('Y/m');
        $originalKey = "photos/originals/{$directory}/{$storedFilename}";
        [$width, $height] = $this->imageSize($file);

        $this->storage->diskForKey()->putFileAs("photos/originals/{$directory}", $file, $storedFilename);

        $autoOcr = (bool) ($attributes['auto_ocr'] ?? false);
        $tagIds = collect($attributes['tag_ids'] ?? [])
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $photo = Photo::query()->create(array_merge([
            'title' => $storedFilename,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'source_url' => null,
            'copyright_status' => 'unknown',
            'status' => 'draft',
            'width' => $width,
            'height' => $height,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'original_key' => $originalKey,
            'uploaded_by' => $uploader?->id,
            'photo_upload_batch_id' => $batch?->id,
        ], Arr::only($attributes, [
            'source_url',
            'copyright_status',
            'publish_after_processing',
        ]), [
            'title' => $storedFilename,
            'status' => 'draft',
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'original_key' => $originalKey,
            'uploaded_by' => $uploader?->id,
            'photo_upload_batch_id' => $batch?->id,
        ]));

        if ($album !== null) {
            $photo->albums()->attach($album->id);
            $photo->categories()->sync($album->categories()->pluck('categories.id')->all());
        } elseif (filled($attributes['category_ids'] ?? null)) {
            $photo->categories()->sync(
                collect($attributes['category_ids'])
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all(),
            );
        }

        $photo->tags()->sync($tagIds);

        app(PhotoProcessingService::class)->createDefaultJobs($photo, $autoOcr);

        return $photo;
    }

    private function makeStoredFilename(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $randomCode = collect(range(1, 6))
            ->map(fn (): string => chr(random_int(65, 90)))
            ->implode('');

        return now()->format('Ymd').'-'.$randomCode.'.'.$extension;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function imageSize(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath());

        if ($size === false) {
            return [null, null];
        }

        return [$size[0], $size[1]];
    }
}
