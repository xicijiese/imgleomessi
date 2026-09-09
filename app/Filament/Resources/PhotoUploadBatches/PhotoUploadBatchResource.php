<?php

namespace App\Filament\Resources\PhotoUploadBatches;

use App\Filament\Resources\PhotoUploadBatches\Pages\ManagePhotoUploadBatches;
use App\Filament\Resources\PhotoUploadBatches\Pages\ManagePhotoUploadBatchPhotos;
use App\Models\Album;
use App\Models\PhotoUploadBatch;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PhotoUploadBatchResource extends Resource
{
    protected static ?string $model = PhotoUploadBatch::class;

    protected static ?string $slug = 'photo-batches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = '图库管理';

    protected static ?string $navigationLabel = '上传任务';

    protected static ?string $modelLabel = '上传任务';

    protected static ?string $pluralModelLabel = '上传任务';

    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'photos as draft_photos_count' => fn (Builder $query): Builder => $query->where('status', 'draft'),
                'photos as published_photos_count' => fn (Builder $query): Builder => $query->where('status', 'published'),
                'processingJobs as pending_processing_jobs_count' => fn (Builder $query): Builder => $query->where('processing_jobs.type', '!=', 'labels')->where('processing_jobs.status', 'pending'),
                'processingJobs as running_processing_jobs_count' => fn (Builder $query): Builder => $query->where('processing_jobs.type', '!=', 'labels')->where('processing_jobs.status', 'running'),
                'processingJobs as failed_processing_jobs_count' => fn (Builder $query): Builder => $query->where('processing_jobs.type', '!=', 'labels')->where('processing_jobs.status', 'failed'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('任务 ID')
                    ->sortable(),
                TextColumn::make('mode')
                    ->label('上传模式')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PhotoUploadBatch::MODES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('album.title')
                    ->label('目标相册')
                    ->placeholder('无')
                    ->searchable(),
                TextColumn::make('uploader.name')
                    ->label('上传者')
                    ->placeholder('系统')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('任务状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PhotoUploadBatch::STATUSES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('total_count')
                    ->label('总数')
                    ->sortable(),
                TextColumn::make('success_count')
                    ->label('成功')
                    ->sortable(),
                TextColumn::make('failed_count')
                    ->label('失败')
                    ->sortable(),
                TextColumn::make('draft_photos_count')
                    ->label('待整理'),
                TextColumn::make('published_photos_count')
                    ->label('已发布'),
                TextColumn::make('pending_processing_jobs_count')
                    ->label('待处理任务')
                    ->sortable(),
                TextColumn::make('running_processing_jobs_count')
                    ->label('处理中任务')
                    ->sortable(),
                TextColumn::make('failed_processing_jobs_count')
                    ->label('失败任务')
                    ->color('danger')
                    ->sortable(),
                TextColumn::make('note')
                    ->label('备注')
                    ->limit(24)
                    ->placeholder('无'),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('任务状态')
                    ->options(PhotoUploadBatch::STATUSES),
                SelectFilter::make('mode')
                    ->label('上传模式')
                    ->options(PhotoUploadBatch::MODES),
                SelectFilter::make('album_id')
                    ->label('目标相册')
                    ->options(fn (): array => Album::query()->orderBy('title')->pluck('title', 'id')->all()),
                SelectFilter::make('uploaded_by')
                    ->label('上传者')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('created_at')
                    ->label('创建时间')
                    ->schema([
                        DatePicker::make('created_from')->label('开始日期'),
                        DatePicker::make('created_until')->label('结束日期'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['created_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('viewPhotos')
                    ->label('查看任务详情')
                    ->url(fn (PhotoUploadBatch $record): string => static::getUrl('photos', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePhotoUploadBatches::route('/'),
            'photos' => ManagePhotoUploadBatchPhotos::route('/{record}/photos'),
        ];
    }
}
