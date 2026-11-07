<?php

namespace App\Console\Commands;

use App\Jobs\GeneratePublicImageVariants;
use App\Models\Photo;
use App\Services\HomepageSettings;
use App\Services\SettingImageVariants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RepairPublicImageVariants extends Command
{
    protected $signature = 'media:repair-variants {--apply : 将候选资源加入image-variants队列} {--local-only : 仅处理public磁盘中已有的源文件}';

    protected $description = '检查并排队补齐前台缩略图/展示图，默认仅报告，不修改数据';

    public function handle(HomepageSettings $settings, SettingImageVariants $variants): int
    {
        $photos = Photo::query()->select(['id', 'original_key'])->whereNotNull('original_key')->where('original_key', '!=', '')
            ->where(function ($query) {
                $query->whereNull('thumbnail_key')->orWhere('thumbnail_key', '')
                    ->orWhereNull('display_key')->orWhere('display_key', '')
                    ->orWhereColumn('thumbnail_key', 'original_key')
                    ->orWhereColumn('display_key', 'original_key')
                    ->orWhereColumn('thumbnail_key', 'display_key');
            })->lazyById(200);
        $sources = collect($variants->pendingSources($settings->formState()));
        if ($this->option('local-only')) {
            $disk = Storage::disk('public');
            $photos = $photos->filter(fn ($photo) => $disk->exists($photo->original_key));
            $sources = $sources->filter(fn ($path) => $disk->exists($path));
        }
        $this->info('候选图片：'.$photos->count().'；独立站点图片：'.$sources->count());
        if (! $this->option('apply')) {
            $this->comment('仅检查。确认范围并备份后使用 --apply；COS处理可能产生费用。');

            return self::SUCCESS;
        }
        foreach ($photos as $photo) {
            GeneratePublicImageVariants::dispatch($photo->id);
        }
        foreach ($sources as $source) {
            GeneratePublicImageVariants::dispatch(null, $source);
        }
        $this->info('已提交入队请求；需Worker监听image-variants队列。原文件及发布状态不变。');

        return self::SUCCESS;
    }
}
