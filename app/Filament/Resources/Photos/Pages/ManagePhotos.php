<?php

namespace App\Filament\Resources\Photos\Pages;

use App\Filament\Resources\Photos\PhotoResource;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\Source;
use App\Models\User;
use App\Services\PhotoBatchOrganizer;
use App\Services\PhotoUploadService;
use App\Services\TencentDataWanxiangService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Http\UploadedFile;

class ManagePhotos extends ManageRecords
{
    protected static string $resource = PhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkUpload')
                ->label('批量上传')
                ->modalHeading('批量上传图片')
                ->modalDescription('可选择上传后保留草稿，或在基础处理完成且满足发布条件后自动发布；OCR 和智能标签可按需自动入队。')
                ->modalSubmitActionLabel('开始上传')
                ->schema([
                    FileUpload::make('files')
                        ->label('图片文件')
                        ->multiple()
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->storeFiles(false)
                        ->required(),
                    Select::make('album_id')
                        ->label('关联相册')
                        ->options(fn (): array => Album::query()
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->helperText('选择相册时，新图片会加入该相册，并默认继承相册分类。'),
                    Select::make('source_id')
                        ->label('来源')
                        ->options(fn (): array => Source::query()
                            ->enabled()
                            ->latest('updated_at')
                            ->get()
                            ->mapWithKeys(fn (Source $source): array => [
                                $source->id => $source->original_url ?: '来源 #'.$source->id,
                            ])
                            ->all())
                        ->searchable()
                        ->preload(),
                    Select::make('copyright_status')
                        ->label('版权状态')
                        ->options(Photo::COPYRIGHT_STATUSES)
                        ->default('unknown')
                        ->required(),
                    Select::make('publish_mode')
                        ->label('上传后的发布状态')
                        ->options([
                            'draft' => '草稿（待整理）',
                            'publish_after_processing' => '处理完成后自动发布',
                        ])
                        ->default('draft')
                        ->live()
                        ->required()
                        ->helperText('自动发布仍会检查标题、版权状态和 7 个固定主分类；不满足条件时会保留为草稿。'),
                    Select::make('category_ids')
                        ->label('批量图片分类')
                        ->options(fn (): array => Category::query()
                            ->children()
                            ->with('parent')
                            ->orderBy('parent_id')
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (Category $category): array => [
                                $category->id => ($category->parent?->name ?? '未分组').' / '.$category->name,
                            ])
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->visible(fn (Get $get): bool => blank($get('album_id')))
                        ->required(fn (Get $get): bool => blank($get('album_id')) && $get('publish_mode') === 'publish_after_processing')
                        ->helperText('未选择相册时可在这里一次性为所有图片指定分类；选择自动发布时必须覆盖 7 个固定主分类。'),
                    Toggle::make('auto_ocr')
                        ->label('上传后自动识别 OCR')
                        ->default(fn (): bool => app(TencentDataWanxiangService::class)->enabled())
                        ->helperText('启用数据万象后将自动创建 OCR 任务；关闭时可稍后在图片管理中手动识别。'),
                    Toggle::make('auto_labels')
                        ->label('上传后自动识别智能标签')
                        ->default(fn (): bool => app(TencentDataWanxiangService::class)->enabled())
                        ->helperText('启用数据万象后将自动创建智能标签任务；关闭时可稍后手动识别。'),
                ])
                ->modalWidth('2xl')
                ->action(function (array $data, PhotoUploadService $photoUploadService): void {
                    $album = filled($data['album_id'] ?? null)
                        ? Album::query()->find($data['album_id'])
                        : null;
                    $publishAfterProcessing = ($data['publish_mode'] ?? 'draft') === 'publish_after_processing';
                    $categoryIds = collect($data['category_ids'] ?? [])
                        ->filter()
                        ->map(fn (mixed $id): int => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                    $autoOcr = (bool) ($data['auto_ocr'] ?? false);
                    $autoLabels = (bool) ($data['auto_labels'] ?? false);

                    if ($publishAfterProcessing
                        && $album === null
                        && ! app(PhotoBatchOrganizer::class)->isCompleteCategorySet($categoryIds)) {
                        Notification::make()
                            ->title('自动发布需要完整分类')
                            ->body('未关联相册时，请从 7 个固定主分类中各选择 1 个子分类。')
                            ->danger()
                            ->send();

                        return;
                    }

                    $uploader = auth()->user();

                    if (! $uploader instanceof User) {
                        $uploader = null;
                    }

                    $files = collect($data['files'] ?? [])
                        ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
                        ->values();

                    $batch = PhotoUploadBatch::query()->create([
                        'mode' => $album === null ? 'standalone' : 'album',
                        'album_id' => $album?->id,
                        'uploaded_by' => $uploader?->id,
                        'status' => 'processing',
                        'total_count' => $files->count(),
                    ]);

                    $successCount = 0;
                    $failedCount = 0;

                    foreach ($files as $file) {
                        try {
                            $photoUploadService->store($file, $album, $uploader, $batch, [
                                'source_id' => filled($data['source_id'] ?? null) ? $data['source_id'] : null,
                                'copyright_status' => $data['copyright_status'] ?? 'unknown',
                                'publish_after_processing' => $publishAfterProcessing,
                                'category_ids' => $categoryIds,
                                'auto_ocr' => $autoOcr,
                                'auto_labels' => $autoLabels,
                            ]);

                            $successCount++;
                        } catch (\Throwable $throwable) {
                            report($throwable);
                            $failedCount++;
                        }
                    }

                    $batch->update([
                        'status' => match (true) {
                            $failedCount === 0 => 'completed',
                            $successCount === 0 => 'failed',
                            default => 'partially_failed',
                        },
                        'success_count' => $successCount,
                        'failed_count' => $failedCount,
                    ]);

                    $notification = Notification::make()
                        ->title("已上传 {$successCount} 张图片")
                        ->body($failedCount > 0
                            ? "{$failedCount} 张上传失败，请在上传批次中排查。"
                            : ($publishAfterProcessing
                                ? '图片已入队处理；满足发布条件后会自动发布，否则保留为草稿。'
                                : '图片已保存为草稿，可继续进入批量整理补充资料。'));

                    if ($failedCount > 0) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
            CreateAction::make(),
        ];
    }
}