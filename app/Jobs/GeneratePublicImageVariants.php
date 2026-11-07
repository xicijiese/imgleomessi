<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Models\Setting;
use App\Services\LocalPhotoDerivativeService;
use App\Services\PhotoStorage;
use App\Services\SettingImageVariants;
use App\Services\TencentDataWanxiangService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GeneratePublicImageVariants implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(public ?int $photoId = null, public ?string $source = null)
    {
        $this->onQueue('image-variants');
        if (config('queue.default') === 'sync') {
            $this->onConnection('database');
        }
    }

    public function uniqueId(): string
    {
        return $this->photoId ? 'photo-'.$this->photoId : 'setting-'.hash('sha256', (string) $this->source);
    }

    public function handle(PhotoStorage $storage, LocalPhotoDerivativeService $local, TencentDataWanxiangService $cos): void
    {
        if ($this->photoId) {
            $photo = Photo::query()->find($this->photoId);
            if (! $photo) {
                return;
            }
        } else {
            if (! $this->source || SettingImageVariants::sourceKey($this->source) !== $this->source
                || SettingImageVariants::keys($this->source)) {
                return;
            }
            $photo = new Photo(['uuid' => 'setting-'.hash('sha256', $this->source), 'original_key' => $this->source]);
        }
        $keys = Storage::disk('public')->exists($photo->original_key) || $storage->activeDiskName() !== 'cos'
            ? $local->generate($photo) : $cos->generateDerivatives($photo);
        if ($this->photoId) {
            // 只更新派生资源，不触发重新发布或修改来源资料。
            $photo->update($keys);
        } else {
            Setting::setValue('image_variants', hash('sha256', $this->source), $keys, null, '站点图片派生资源');
        }
    }
}
