<?php

namespace App\Filament\Resources\PhotoUploadBatches\Pages;

use App\Filament\Resources\PhotoUploadBatches\PhotoUploadBatchResource;
use App\Filament\Resources\Photos\PhotoResource;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Services\PhotoStorage;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManagePhotoUploadBatchPhotos extends ManageRelatedRecords
{
    protected static string $resource = PhotoUploadBatchResource::class;

    protected static string $relationship = 'photos';

    protected static ?string $navigationLabel = '上传任务详情';

    protected static ?string $breadcrumb = '上传任务详情';

    public function getTitle(): string
    {
        return '上传任务 #'.$this->getOwnerRecord()->getKey();
    }

    public function getSubheading(): ?string
    {
        $batch = $this->getOwnerRecord();
        $summary = "总数 {$batch->total_count}，成功 {$batch->success_count}，失败 {$batch->failed_count}";

        return filled($batch->note) ? $summary.'；'.$batch->note : $summary;
    }

    public function table(Table $table): Table
    {
        $batch = $this->getOwnerRecord();

        return $table
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('缩略图')
                    ->state(fn (Photo $record): ?string => app(PhotoStorage::class)->url($record->thumbnail_key ?: $record->display_key))
                    ->imageSize(64)
                    ->square()
                    ->checkFileExistence(false),
                TextColumn::make('title')
                    ->label('已创建图片')
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
                TextColumn::make('event_date')
                    ->label('事件日期')
                    ->date('Y-m-d')
                    ->placeholder('未填写'),
                TextColumn::make('categories_count')
                    ->label('分类数')
                    ->counts('categories'),
            ])
            ->headerActions([
                Action::make('viewPhotos')
                    ->label('在图片管理中查看')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->url(PhotoResource::getUrl(parameters: [
                        'tableFilters' => [
                            'photo_upload_batch_id' => [
                                'value' => $batch->id,
                            ],
                        ],
                    ])),
                Action::make('backToTasks')
                    ->label('返回上传任务')
                    ->icon(Heroicon::OutlinedArrowLeft)
                    ->url(PhotoUploadBatchResource::getUrl()),
            ]);
    }
}