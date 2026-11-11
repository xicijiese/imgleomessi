<?php

namespace App\Filament\Resources\Photos\Pages;

use App\Filament\Resources\Photos\PhotoResource;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\Tag;
use App\Models\User;
use App\Services\PhotoBatchOrganizer;
use App\Services\PhotoUploadService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Http\UploadedFile;

class ManagePhotos extends ManageRecords
{
    protected static string $resource = PhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkUpload')
                ->label('上传图片')
                ->modalHeading('上传图片')
                ->modalDescription('支持上传单张或多张图片。可选择保留草稿，或在基础处理完成且满足发布条件后自动发布。')
                ->modalSubmitActionLabel('开始上传')
                ->schema([
                    Grid::make(2)->schema([
                        FileUpload::make('files')
                            ->columnSpanFull()
                            ->label('图片文件')
                            ->multiple()
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->storeFiles(false)
                            ->required(),
                        Section::make('图片分类与相册')
                            ->schema([
                                ...self::categorySelectors(),
                                Select::make('album_id')
                                    ->label('关联相册')
                                    ->options(fn (): array => Album::query()
                                        ->orderBy('title')
                                        ->pluck('title', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->columnSpanFull()
                                    ->helperText('可直接选择分类，也可以只选择相册；选择相册后图片自动继承相册分类。'),
                                Select::make('tag_ids')
                                    ->columnSpanFull()
                                    ->label('标签')
                                    ->options(fn (): array => Tag::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('标签名')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(table: 'tags', column: 'name'),
                                    ])
                                    ->createOptionUsing(fn (array $data): int => (int) Tag::query()->create($data)->getKey())
                                    ->helperText('可输入关键词搜索，输入不存在的标签后可直接创建。'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                        Section::make('来源与发布')->schema([
                            TextInput::make('source_url')
                                ->columnSpanFull()
                                ->label('来源链接')
                                ->url()
                                ->maxLength(2048)
                                ->helperText('可选；来源平台请在分类中选择。'),
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
                                ->default('publish_after_processing')
                                ->live()
                                ->required()
                                ->helperText('默认处理完成后自动发布；仍会检查标题、版权状态和必选主分类，不满足条件时会保留为草稿。'),
                        ])->columns(2)->columnSpanFull(),
                    ])->columnSpanFull(),
                ])
                ->modalWidth('5xl')
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->action(function (array $data, PhotoUploadService $photoUploadService): void {
                    $album = filled($data['album_id'] ?? null)
                        ? Album::query()->find($data['album_id'])
                        : null;
                    $publishAfterProcessing = ($data['publish_mode'] ?? 'publish_after_processing') === 'publish_after_processing';
                    $categoryIds = self::categoryIdsFromData($data);

                    if ($publishAfterProcessing
                        && $album === null
                        && ! app(PhotoBatchOrganizer::class)->isCompleteCategorySet($categoryIds)) {
                        Notification::make()
                            ->title('自动发布需要完整分类')
                            ->body('未关联相册时，请补齐生涯阶段、年份和场景三个必选主分类。')
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
                    $failureDetails = [];

                    foreach ($files as $file) {
                        try {
                            $photoUploadService->store($file, $album, $uploader, $batch, [
                                'source_url' => filled($data['source_url'] ?? null) ? $data['source_url'] : null,
                                'copyright_status' => $data['copyright_status'] ?? 'unknown',
                                'publish_after_processing' => $publishAfterProcessing,
                                'category_ids' => $categoryIds,
                                'tag_ids' => $data['tag_ids'] ?? [],
                            ]);

                            $successCount++;
                        } catch (\Throwable $throwable) {
                            report($throwable);
                            $failedCount++;
                            $failureDetails[] = $file->getClientOriginalName().'：'.str($throwable->getMessage())->limit(240)->toString();
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
                        'note' => $failureDetails === [] ? null : '失败文件：'.implode('；', $failureDetails),
                    ]);

                    $notification = Notification::make()
                        ->title("已上传 {$successCount} 张图片")
                        ->body($failedCount > 0
                            ? "{$failedCount} 张上传失败，请到上传任务查看失败文件和原因。"
                            : ($publishAfterProcessing
                                ? '图片已入队处理；满足发布条件后会自动发布，否则保留为草稿。'
                                : '图片已保存为草稿，请到图片管理继续补充资料。'));

                    if ($failedCount > 0) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function categorySelectors(): array
    {
        return Category::query()
            ->roots()
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Category $root): Select {
                return Select::make(self::categoryFieldName((int) $root->id))
                    ->label($root->name)
                    ->multiple(in_array($root->slug, Category::MULTI_SELECT_ROOT_SLUGS, true))
                    ->options($root->children
                        ->where('visibility', 'public')
                        ->mapWithKeys(fn (Category $child): array => [$child->id => $child->name])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->markAsRequired($root->required_for_publish)
                    ->visible(fn (Get $get): bool => blank($get('album_id')))
                    ->helperText(in_array($root->slug, Category::MULTI_SELECT_ROOT_SLUGS, true)
                        ? ($root->required_for_publish ? '自动发布前至少选择一项，可多选。' : '可选，支持多选。')
                        : ($root->required_for_publish ? '自动发布前必选。' : '可选分类。'));
            })
            ->all();
    }

    private static function categoryFieldName(int $rootId): string
    {
        return 'category_root_'.$rootId;
    }

    /**
     * @return array<int, int>
     */
    private static function categoryIdsFromData(array $data): array
    {
        return Category::query()
            ->roots()
            ->pluck('id')
            ->map(fn (mixed $id): string => self::categoryFieldName((int) $id))
            ->flatMap(fn (string $field): array => (array) ($data[$field] ?? []))
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
