<?php

namespace App\Filament\Resources\PhotoDeduplicationLogs;

use App\Filament\Resources\PhotoDeduplicationLogs\Pages\ManagePhotoDeduplicationLogs;
use App\Models\Photo;
use App\Models\PhotoDeduplicationLog;
use App\Services\PhotoSimilarityGovernance;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PhotoDeduplicationLogResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = PhotoDeduplicationLog::class;

    protected static string|UnitEnum|null $navigationGroup = '图库管理';

    protected static ?string $navigationLabel = '重复图归档记录';

    protected static ?string $modelLabel = '重复图归档记录';

    protected static ?int $navigationSort = 87;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('photo.title')->label('归档图片')->searchable(),
            TextColumn::make('retainedPhoto.title')->label('保留图片'),
            TextColumn::make('sha256_hash')->label('SHA-256')->limit(16)->copyable(),
            TextColumn::make('previous_status')->label('归档前状态')->formatStateUsing(fn ($state) => Photo::STATUSES[$state] ?? $state),
            TextColumn::make('created_at')->label('归档时间')->dateTime('Y-m-d H:i'),
            TextColumn::make('restored_at')->label('恢复时间')->dateTime('Y-m-d H:i')->placeholder('未恢复'),
        ])->defaultSort('id', 'desc')->recordActions([
            Action::make('restore')->label('恢复为草稿')->requiresConfirmation()
                ->modalDescription('恢复图片供人工检查，不自动重新发布；恢复后后续去重任务会保留该独立记录。')
                ->visible(fn ($record) => $record->restored_at === null)
                ->action(function ($record): void {
                    abort_unless(auth()->user()?->isAdministrator(), 403);
                    app(PhotoSimilarityGovernance::class)->restore($record, auth()->user());
                }),
        ])->emptyStateHeading('暂无重复图归档记录');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePhotoDeduplicationLogs::route('/')];
    }
}
