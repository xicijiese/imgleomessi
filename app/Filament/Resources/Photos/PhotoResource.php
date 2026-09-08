<?php

namespace App\Filament\Resources\Photos;

use App\Filament\Resources\PhotoSimilarityCandidates\PhotoSimilarityCandidateResource;
use App\Filament\Resources\Photos\Pages\ManagePhotos;
use App\Models\Category;
use App\Models\Opponent;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\Source;
use App\Models\User;
use App\Services\PhotoProcessingService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
        return parent::getEloquentQuery()->with('analysisResult');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                            ->label('事件日期'),
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
                        Select::make('status')
                            ->label('发布状态')
                            ->options(Photo::STATUSES)
                            ->default('draft')
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('发布时间')
                            ->seconds(false),
                    ])
                    ->columns(2),
                Section::make('分类与标签')
                    ->schema([
                        Select::make('categories')
                            ->label('图片分类')
                            ->relationship(
                                name: 'categories',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->children()
                                    ->with('parent')
                                    ->orderBy('parent_id')
                                    ->orderBy('sort_order')
                                    ->orderBy('id'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Category $record): string => ($record->parent?->name ?? '未分组').' / '.$record->name)
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->rules([self::completeCategorySetRule()])
                            ->helperText('单独上传图片发布前必须从 7 个固定主分类中各选择 1 个子分类；不知道的信息选择待补充。'),
                        Select::make('tags')
                            ->label('标签')
                            ->relationship(name: 'tags', titleAttribute: 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('标签可为空，用于补充动作、情绪、画质、人物关系等细节。'),
                        Select::make('opponents')
                            ->label('对手')
                            ->relationship(
                                name: 'opponents',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->orderBy('id'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Opponent $record): string => filled($record->country) ? $record->name.' / '.$record->country : $record->name)
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('可为空，仅用于对手聚合页；不会替代 7 个固定主分类。'),
                        Select::make('opponents')
                            ->label('对手')
                            ->relationship(
                                name: 'opponents',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->orderBy('id'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Opponent $record): string => filled($record->country) ? $record->name.' / '.$record->country : $record->name)
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('可为空，仅用于对手聚合页；不会替代 7 个固定主分类。'),
                    ]),
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('thumbnail_key')
                    ->label('缩略图')
                    ->limit(20)
                    ->placeholder('未生成'),
                TextColumn::make('title')
                    ->label('标题')
                    ->sortable()
                    ->searchable(),
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
                TextColumn::make('analysisResult.ocr_text')
                    ->label('OCR 文本')
                    ->limit(42)
                    ->placeholder('未识别'),
                TextColumn::make('analysisResult.ci_labels_json')
                    ->label('智能标签')
                    ->state(fn (Photo $record): string => collect($record->analysisResult?->ci_labels_json ?? [])
                        ->pluck('name')
                        ->filter()
                        ->implode('、'))
                    ->limit(42)
                    ->placeholder('未识别'),                TextColumn::make('analysisResult.sha256_hash')
                    ->label('SHA-256')
                    ->limit(12)
                    ->placeholder('未分析'),
                TextColumn::make('duplicate_warning')
                    ->label('重复提示')
                    ->state(fn (Photo $record): string => app(PhotoProcessingService::class)->duplicateSummary($record))
                    ->badge()
                    ->color(fn (string $state): string => str_starts_with($state, '可能重复') ? 'warning' : ($state === '无重复' ? 'success' : 'gray')),
                TextColumn::make('source.original_url')
                    ->label('来源')
                    ->limit(36)
                    ->placeholder('未填写')
                    ->searchable(),
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
                SelectFilter::make('source_id')
                    ->label('来源')
                    ->options(fn (): array => Source::query()
                        ->latest('updated_at')
                        ->get()
                        ->mapWithKeys(fn (Source $source): array => [
                            $source->id => $source->original_url ?: '来源 #'.$source->id,
                        ])
                        ->all()),
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
            ->recordActions([
                Action::make('ocr')
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
                Action::make('labels')
                    ->label('识别标签')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Photo $record): void {
                        $job = app(PhotoProcessingService::class)->queueLabels($record);

                        Notification::make()
                            ->title('智能标签任务已入队')
                            ->body('处理任务 #'.$job->id.' 已创建，可在“图片入库处理”中查看。')
                            ->success()
                            ->send();
                    }),                Action::make('clearOcr')
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
                Action::make('clearLabels')
                    ->label('清空标签')
                    ->color('warning')
                    ->visible(fn (Photo $record): bool => filled($record->analysisResult?->ci_labels_json))
                    ->requiresConfirmation()
                    ->action(function (Photo $record): void {
                        app(PhotoProcessingService::class)->clearLabels($record);

                        Notification::make()
                            ->title('智能标签已清空')
                            ->success()
                            ->send();
                    }),                Action::make('similarity')
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
                EditAction::make(),
                Action::make('publish')
                    ->label('发布')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Photo $record): bool => $record->status !== 'published')
                    ->disabled(fn (Photo $record): bool => ! $record->canBePublished())
                    ->action(fn (Photo $record): bool => $record->publish()),
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

    private static function completeCategorySetRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $categoryIds = collect($value)->filter()->map(fn (mixed $id): int => (int) $id)->unique();
            $rootCount = Category::query()->roots()->count();

            if ($categoryIds->isEmpty()) {
                return;
            }

            if ($rootCount === 0 || $categoryIds->count() !== $rootCount) {
                $fail('图片发布前必须从每个固定主分类下各选择 1 个子分类。');

                return;
            }

            $selectedParentCount = Category::query()
                ->whereIn('id', $categoryIds)
                ->children()
                ->distinct()
                ->count('parent_id');

            if ($selectedParentCount !== $rootCount) {
                $fail('图片发布前必须从每个固定主分类下各选择 1 个子分类。');
            }
        };
    }
}
