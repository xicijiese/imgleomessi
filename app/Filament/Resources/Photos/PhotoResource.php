<?php

namespace App\Filament\Resources\Photos;

use App\Filament\Resources\Photos\Pages\ManagePhotos;
use App\Filament\Resources\PhotoSimilarityCandidates\PhotoSimilarityCandidateResource;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\Tag;
use App\Models\User;
use App\Services\PhotoBatchOrganizer;
use App\Services\PhotoProcessingService;
use App\Services\PhotoStorage;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use UnitEnum;

class PhotoResource extends Resource
{
    protected static ?string $model = Photo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = '图库管理';

    protected static ?string $navigationLabel = '图片管理';

    protected static ?string $modelLabel = '图片';

    protected static ?string $pluralModelLabel = '图片';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['analysisResult', 'categories.parent']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::formComponents());
    }

    /**
     * @return array<int, mixed>
     */
    private static function formComponents(): array
    {
        return [
            Section::make('基础信息')
                ->schema([
                    TextInput::make('title')
                        ->label('标题')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('说明')
                        ->rows(4)
                        ->columnSpanFull(),
                    DateTimePicker::make('taken_at')
                        ->label('拍摄时间')
                        ->seconds(false),
                    DatePicker::make('event_date')
                        ->label('事件日期'),                    TextInput::make('source_url')
                        ->label('来源链接')
                        ->url()
                        ->maxLength(2048)
                        ->helperText('可选；来源平台请在分类中选择。'),
                    Select::make('uploaded_by')
                        ->label('上传者')
                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload(),
                    Select::make('copyright_status')
                        ->label('版权状态')
                        ->options(Photo::COPYRIGHT_STATUSES)
                        ->default('unknown')
                        ->required(),
                    TextInput::make('status')
                        ->label('发布状态')
                        ->formatStateUsing(fn (?string $state): string => Photo::STATUSES[$state ?? 'draft'] ?? '草稿')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('发布状态只能通过列表中的“发布”、“归档”或“恢复为草稿”操作变更。'),
                    DateTimePicker::make('published_at')
                        ->label('发布时间')
                        ->seconds(false)
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('未发布时显示“未发布”；发布时间由系统在实际发布时写入。'),
                ])
                ->columns(2),
            Section::make('分类与标签')
                ->schema([
                    Section::make('图片分类与相册')
                        ->schema([
                            ...self::categorySelectors(),
                            Select::make('albums')
                                ->label('关联相册')
                                ->relationship(name: 'albums', titleAttribute: 'title')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->helperText('可直接选择分类，也可以只选择相册；选择相册后图片自动继承相册分类。'),
                        ])
                        ->columns(2),                    Select::make('tags')
                        ->label('标签')
                        ->relationship(name: 'tags', titleAttribute: 'name')
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
                        ->helperText('输入关键词搜索；输入不存在的标签后可直接创建.'),
                ])
                ->columns(2),
            Section::make('文件信息')
                ->schema([
                    TextInput::make('original_filename')
                        ->label('原始文件名')
                        ->maxLength(255),
                    TextInput::make('stored_filename')
                        ->label('系统文件名')
                        ->maxLength(255),
                    TextInput::make('original_key')
                        ->label('原图存储 Key')
                        ->maxLength(255),
                    TextInput::make('display_key')
                        ->label('展示图存储 Key')
                        ->maxLength(255),
                    TextInput::make('thumbnail_key')
                        ->label('缩略图存储 Key')
                        ->maxLength(255),
                    TextInput::make('mime_type')
                        ->label('MIME')
                        ->maxLength(255),
                    TextInput::make('file_size')
                        ->label('文件大小')
                        ->numeric(),
                    TextInput::make('width')
                        ->label('宽度')
                        ->numeric(),
                    TextInput::make('height')
                        ->label('高度')
                        ->numeric(),
                ])
                ->columns(3),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('thumbnail')
                    ->label('缩略图')
                    ->view('filament.tables.columns.photo-thumbnail', fn (Photo $record): array => [
                        'thumbnailUrl' => app(PhotoStorage::class)->url($record->thumbnail_key ?: $record->display_key),
                        'previewUrl' => app(PhotoStorage::class)->url($record->display_key ?: $record->thumbnail_key),
                        'alt' => $record->title ?: $record->original_filename ?: '图片',
                    ]),
                TextColumn::make('title')
                    ->label('标题')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('category_summary')
                    ->label('分类')
                    ->state(fn (Photo $record): string => $record->categories
                        ->map(fn (Category $category): string => $category->parent ? $category->parent->name.' / '.$category->name : $category->name)
                        ->take(3)
                        ->implode('、'))
                    ->limit(32)
                    ->placeholder('待补充'),
                TextColumn::make('status')
                    ->label('发布状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Photo::STATUSES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('copyright_status')
                    ->label('版权状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Photo::COPYRIGHT_STATUSES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('event_date')
                    ->label('事件日期')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('未填写'),
                TextColumn::make('published_at')
                    ->label('发布时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('未发布'),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('发布状态')
                    ->options(Photo::STATUSES),
                SelectFilter::make('copyright_status')
                    ->label('版权状态')
                    ->options(Photo::COPYRIGHT_STATUSES),
                SelectFilter::make('photo_upload_batch_id')
                    ->label('上传批次')
                    ->options(fn (): array => PhotoUploadBatch::query()
                        ->latest('created_at')
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(fn (PhotoUploadBatch $batch): array => [
                            $batch->id => '批次 #'.$batch->id,
                        ])
                        ->all()),
            ])
            ->defaultSort('updated_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkSourceUrl')
                        ->label('批量修改来源链接')
                        ->schema([
                            TextInput::make('source_url')
                                ->label('来源链接')
                                ->url()
                                ->maxLength(2048)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->updateCommonFields($records, ['source_url' => $data['source_url']]);
                            Notification::make()->title('已更新 '.$result['updated'].' 张图片的来源链接')->success()->send();
                        }),
                    BulkAction::make('bulkCopyrightStatus')
                        ->label('批量修改版权状态')
                        ->schema([
                            Select::make('copyright_status')
                                ->label('版权状态')
                                ->options(Photo::COPYRIGHT_STATUSES)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->updateCommonFields($records, ['copyright_status' => $data['copyright_status']]);
                            Notification::make()->title('已更新 '.$result['updated'].' 张图片的版权状态')->success()->send();
                        }),
                    BulkAction::make('appendTags')
                        ->label('批量追加标签')
                        ->schema([
                            Select::make('tag_ids')
                                ->label('标签')
                                ->relationship(name: 'tags', titleAttribute: 'name')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->appendTags($records, $data['tag_ids'] ?? []);
                            Notification::make()->title('已为 '.$result['updated'].' 张图片追加标签')->success()->send();
                        }),
                    BulkAction::make('bulkCategories')
                        ->label('批量设置分类')
                        ->schema([
                            Section::make('分类')
                                ->schema(self::categorySelectors())
                                ->columns(2),
                        ])
                        ->action(function (Collection $records, array $data, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->syncStandaloneCategories($records, self::categoryIdsFromFormData($data));

                            if ($result['failed'] > 0) {
                                Notification::make()
                                    ->title('分类设置失败')
                                    ->body('请补齐生涯阶段、年份和场景三个必选主分类。')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()->title('已更新 '.$result['updated'].' 张图片的分类')->success()->send();
                        }),
                    BulkAction::make('publishSelected')
                        ->label('批量确认发布')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->publish($records);
                            $body = $result['failed'] > 0
                                ? implode('；', array_slice($result['failures'], 0, 5))
                                : '选中图片已通过发布条件检查。';
                            $notification = Notification::make()
                                ->title('已发布 '.$result['published'].' 张，失败 '.$result['failed'].' 张')
                                ->body($body);

                            ($result['failed'] > 0 ? $notification->warning() : $notification->success())->send();
                        }),
                    BulkAction::make('setDraft')
                        ->label('批量改为草稿')
                        ->action(function (Collection $records, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->updateCommonFields($records, [
                                'status' => 'draft',
                                'published_at' => null,
                                'publish_after_processing' => false,
                            ]);
                            Notification::make()->title('已将 '.$result['updated'].' 张图片改为草稿')->success()->send();
                        }),
                    BulkAction::make('archiveSelected')
                        ->label('批量归档')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $count = $records->filter(fn (Photo $photo): bool => $photo->archive())->count();
                            Notification::make()->title('已归档 '.$count.' 张图片')->success()->send();
                        }),
                ]),
            ])
            ->recordActions([
                Action::make('derivatives')
                    ->label('生成展示图/缩略图')
                    ->color('info')
                    ->visible(fn (Photo $record): bool => blank($record->display_key) || blank($record->thumbnail_key))
                    ->requiresConfirmation()
                    ->action(function (Photo $record, PhotoProcessingService $service): void {
                        $job = $service->queueDerivatives($record);

                        Notification::make()
                            ->title('展示图/缩略图任务已入队')
                            ->body('处理任务 #'.$job->id.' 将根据当前存储方式生成图片。')
                            ->success()
                            ->send();
                    }),                Action::make('ocr')
                    ->label('识别 OCR')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Photo $record): void {
                        $job = app(PhotoProcessingService::class)->queueOcr($record);

                        Notification::make()
                            ->title('OCR 任务已入队')
                            ->body('处理任务 #'.$job->id.' 已创建，可在“图片入库处理”中查看。')
                            ->success()
                            ->send();
                    }),
                Action::make('clearOcr')
                    ->label('清空 OCR')
                    ->color('warning')
                    ->visible(fn (Photo $record): bool => filled($record->analysisResult?->ocr_text))
                    ->requiresConfirmation()
                    ->action(function (Photo $record): void {
                        app(PhotoProcessingService::class)->clearOcr($record);

                        Notification::make()
                            ->title('OCR 文本已清空')
                            ->success()
                            ->send();
                    }),
                Action::make('similarity')
                    ->label('计算相似候选')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Photo $record): void {
                        $job = app(PhotoProcessingService::class)->queueSimilarity($record);

                        Notification::make()
                            ->title('相似候选计算已入队')
                            ->body('处理任务 #'.$job->id.' 已创建，可在“图片入库处理”中查看。')
                            ->success()
                            ->send();
                    }),
                Action::make('similarityCandidates')
                    ->label('查看相似候选')
                    ->url(fn (Photo $record): string => PhotoSimilarityCandidateResource::getUrl(parameters: [
                        'tableFilters' => [
                            'photo' => [
                                'photo_id' => $record->id,
                            ],
                        ],
                    ])),
                EditAction::make()
                    ->schema(self::formComponents())
                    ->mutateRecordDataUsing(function (array $data): array {
                        $photo = Photo::query()->findOrFail((int) $data['id']);
                        foreach (Category::query()->roots()->orderBy('sort_order')->orderBy('id')->get() as $root) {
                            $data[self::categoryFieldName((int) $root->id)] = $photo->categories()
                                ->where('parent_id', $root->id)
                                ->value('categories.id');
                        }

                        $data['tags'] = $photo->tags()->pluck('tags.id')->all();

                        $data['albums'] = $photo->albums()->pluck('albums.id')->all();

                        return $data;
                    })
                    ->using(function (Photo $record, array $data): Photo {
                        $categoryFieldNames = self::categoryFieldNames();
                        $record->update(Arr::except($data, [...$categoryFieldNames, 'tags', 'albums']));
                        $albumIds = collect($data['albums'] ?? [])
                            ->filter()
                            ->map(fn (mixed $id): int => (int) $id)
                            ->unique()
                            ->values();
                        $record->albums()->sync($albumIds);
                        $categoryIds = $albumIds->isNotEmpty()
                            ? Album::query()
                                ->whereIn('id', $albumIds)
                                ->with('categories')
                                ->get()
                                ->flatMap(fn (Album $album): array => $album->categories->pluck('id')->all())
                                ->unique()
                                ->values()
                                ->all()
                            : self::categoryIdsFromFormData($data);
                        $record->categories()->sync($categoryIds);
                        $record->tags()->sync($data['tags'] ?? []);

                        return $record;
                    }),
                Action::make('publish')
                    ->label('发布')
                    ->color('success')
                    ->requiresConfirmation()
                    ->disabled(fn (Photo $record): bool => $record->status !== 'draft')
                    ->action(function (Photo $record, PhotoBatchOrganizer $organizer): void {
                        $result = $organizer->publish(collect([$record]));

                        if ($result['published'] > 0) {
                            Notification::make()
                                ->title('图片已发布')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('图片发布失败')
                            ->body($result['failures'][0] ?? '图片不满足发布条件。')
                            ->danger()
                            ->send();
                    }),
                Action::make('archive')
                    ->label('归档')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Photo $record): bool => $record->status !== 'archived')
                    ->action(fn (Photo $record): bool => $record->archive()),
                Action::make('restore')
                    ->label('恢复为草稿')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Photo $record): bool => $record->status === 'archived')
                    ->action(fn (Photo $record): bool => $record->restoreFromArchive()),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePhotos::route('/'),
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
                    ->options($root->children
                        ->where('visibility', 'public')
                        ->mapWithKeys(fn (Category $child): array => [$child->id => $child->name])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->markAsRequired($root->required_for_publish)
                    ->visible(fn (Get $get): bool => blank($get('albums')))
                    ->helperText($root->required_for_publish ? '发布前必选一个子分类。' : '可选；有可靠资料时再补充。');
            })
            ->all();
    }

    private static function categoryFieldName(int $rootId): string
    {
        return 'category_root_'.$rootId;
    }

    /**
     * @return array<int, string>
     */
    private static function categoryFieldNames(): array
    {
        return Category::query()
            ->roots()
            ->pluck('id')
            ->map(fn (mixed $id): string => self::categoryFieldName((int) $id))
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private static function categoryIdsFromFormData(array $data): array
    {
        return collect(self::categoryFieldNames())
            ->map(fn (string $field): mixed => $data[$field] ?? null)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function completeCategorySetRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $categoryIds = collect($value)->filter()->map(fn (mixed $id): int => (int) $id)->unique();

            if ($categoryIds->isEmpty()) {
                return;
            }

            $categories = Category::query()
                ->whereIn('id', $categoryIds)
                ->children()
                ->get(['id', 'parent_id']);

            if ($categories->count() !== $categoryIds->count()) {
                $fail('只能选择子分类。');

                return;
            }

            $selectedByParent = $categories->groupBy('parent_id');

            foreach (Category::requiredRootIds() as $rootId) {
                if ($selectedByParent->get($rootId, collect())->count() !== 1) {
                    $fail('发布前必须选择生涯阶段、年份和场景各一个子分类。');

                    return;
                }
            }

            if ($selectedByParent->contains(fn ($selected): bool => $selected->count() > 1)) {
                $fail('每个主分类最多选择一个子分类。');
            }
        };
    }
}
