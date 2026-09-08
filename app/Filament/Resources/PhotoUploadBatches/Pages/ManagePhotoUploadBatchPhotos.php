<?php

namespace App\Filament\Resources\PhotoUploadBatches\Pages;

use App\Filament\Resources\PhotoUploadBatches\PhotoUploadBatchResource;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\Source;
use App\Models\Tag;
use App\Services\PhotoBatchOrganizer;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ManagePhotoUploadBatchPhotos extends ManageRelatedRecords
{
    protected static string $resource = PhotoUploadBatchResource::class;

    protected static string $relationship = 'photos';

    protected static ?string $navigationLabel = '批量整理';

    protected static ?string $breadcrumb = '批量整理';

    public function getTitle(): string
    {
        return '批量整理批次 #'.$this->getOwnerRecord()->getKey();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->photoFormComponents($this->getOwnerRecord()));
    }

    public function table(Table $table): Table
    {
        $batch = $this->getOwnerRecord();

        return $table
            ->columns([
                TextColumn::make('original_key')
                    ->label('原图 Key')
                    ->limit(24)
                    ->placeholder('无'),
                TextColumn::make('original_filename')
                    ->label('原始文件名')
                    ->limit(24)
                    ->searchable(),
                TextColumn::make('stored_filename')
                    ->label('系统文件名')
                    ->limit(24)
                    ->searchable(),
                TextInputColumn::make('title')
                    ->label('标题')
                    ->rules(['required', 'max:255'])
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
                TextColumn::make('source.original_url')
                    ->label('来源')
                    ->limit(28)
                    ->placeholder('未填写')
                    ->searchable(),
                TextColumn::make('event_date')
                    ->label('事件日期')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('未填写'),
                TextColumn::make('albums_count')
                    ->label('相册数')
                    ->counts('albums'),
                TextColumn::make('categories_count')
                    ->label('分类数')
                    ->counts('categories'),
                TextColumn::make('tags_count')
                    ->label('标签数')
                    ->counts('tags'),
                TextColumn::make('width')
                    ->label('宽')
                    ->placeholder('-'),
                TextColumn::make('height')
                    ->label('高')
                    ->placeholder('-'),
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
            ])
            ->headerActions([
                Action::make('publishReady')
                    ->label('发布当前批次可发布图片')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (PhotoBatchOrganizer $organizer): void {
                        $result = $organizer->publish($this->getOwnerRecord()->photos()->where('status', 'draft')->get());
                        $this->sendPublishNotification($result);
                    }),
                Action::make('backToBatches')
                    ->label('返回批次列表')
                    ->url(PhotoUploadBatchResource::getUrl()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('单张编辑')
                    ->schema(fn (): array => $this->photoFormComponents($batch)),
                Action::make('publish')
                    ->label('发布')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Photo $record): bool => $record->status === 'draft')
                    ->action(function (Photo $record, PhotoBatchOrganizer $organizer): void {
                        $this->sendPublishNotification($organizer->publish(collect([$record])));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkSource')
                        ->label('批量补来源')
                        ->schema([
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
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->updateCommonFields($records, ['source_id' => $data['source_id']]);
                            $this->sendUpdatedNotification($result['updated']);
                        }),
                    BulkAction::make('bulkCopyrightStatus')
                        ->label('批量补版权状态')
                        ->schema([
                            Select::make('copyright_status')
                                ->label('版权状态')
                                ->options(Photo::COPYRIGHT_STATUSES)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->updateCommonFields($records, ['copyright_status' => $data['copyright_status']]);
                            $this->sendUpdatedNotification($result['updated']);
                        }),
                    BulkAction::make('bulkEventDate')
                        ->label('批量补事件日期')
                        ->schema([
                            DatePicker::make('event_date')
                                ->label('事件日期')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->updateCommonFields($records, ['event_date' => $data['event_date']]);
                            $this->sendUpdatedNotification($result['updated']);
                        }),
                    BulkAction::make('appendTags')
                        ->label('批量追加标签')
                        ->schema([
                            Select::make('tag_ids')
                                ->label('标签')
                                ->options(fn (): array => Tag::query()->orderBy('type')->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->appendTags($records, $data['tag_ids'] ?? []);
                            $this->sendUpdatedNotification($result['updated']);
                        }),
                    BulkAction::make('bulkCategories')
                        ->label('批量设置分类')
                        ->visible($batch->mode === 'standalone')
                        ->schema([
                            Select::make('category_ids')
                                ->label('分类')
                                ->options(fn (): array => $this->categoryOptions())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('必须从 7 个固定主分类中各选择 1 个子分类；不知道的信息选择待补充。'),
                        ])
                        ->action(function (Collection $records, array $data, PhotoBatchOrganizer $organizer): void {
                            $result = $organizer->syncStandaloneCategories($records, $data['category_ids'] ?? []);

                            if ($result['failed'] > 0) {
                                Notification::make()
                                    ->title('分类设置失败')
                                    ->body('必须从 7 个固定主分类中各选择 1 个子分类。')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $this->sendUpdatedNotification($result['updated']);
                        }),
                    BulkAction::make('publishSelected')
                        ->label('发布选中图片')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, PhotoBatchOrganizer $organizer): void {
                            $this->sendPublishNotification($organizer->publish($records));
                        }),
                ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function categoryOptions(): array
    {
        return Category::query()
            ->children()
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => ($category->parent?->name ?? '未分组').' / '.$category->name,
            ])
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    private function photoFormComponents(PhotoUploadBatch $batch): array
    {
        return [
            TextInput::make('title')
                ->label('标题')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label('说明')
                ->rows(3)
                ->columnSpanFull(),
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
            Select::make('copyright_status')
                ->label('版权状态')
                ->options(Photo::COPYRIGHT_STATUSES)
                ->required(),
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
                ->visible($batch->mode === 'standalone')
                ->helperText('单独上传图片发布前必须从 7 个固定主分类中各选择 1 个子分类。'),
            Select::make('tags')
                ->label('标签')
                ->relationship(name: 'tags', titleAttribute: 'name')
                ->multiple()
                ->preload()
                ->searchable(),
        ];
    }

    private function sendUpdatedNotification(int $updated): void
    {
        Notification::make()
            ->title("已更新 {$updated} 张图片")
            ->success()
            ->send();
    }

    /**
     * @param  array{published: int, failed: int, failures: array<int, string>}  $result
     */
    private function sendPublishNotification(array $result): void
    {
        $notification = Notification::make()
            ->title("已发布 {$result['published']} 张图片")
            ->body($result['failed'] > 0 ? implode('；', array_slice($result['failures'], 0, 3)) : '满足条件的图片已发布。');

        if ($result['failed'] > 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }
}
