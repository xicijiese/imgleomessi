<?php

namespace App\Filament\Resources\PhotoGovernanceRuns;

use App\Filament\Resources\PhotoGovernanceRuns\Pages\ManagePhotoGovernanceRuns;
use App\Jobs\ProcessPhotoGovernance;
use App\Models\PhotoGovernanceRun;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PhotoGovernanceRunResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = PhotoGovernanceRun::class;

    protected static string|UnitEnum|null $navigationGroup = '图库管理';

    protected static ?string $navigationLabel = '相似图处理进度';

    protected static ?string $modelLabel = '相似图处理任务';

    protected static ?int $navigationSort = 88;

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
            TextColumn::make('type')->label('任务')->formatStateUsing(fn ($state) => $state === 'groups' ? '分析与分组' : '精确重复图归档'),
            TextColumn::make('status')->label('状态')->badge()->formatStateUsing(fn ($state) => ['pending' => '排队中', 'running' => '处理中', 'done' => '已完成', 'failed' => '失败'][$state] ?? $state),
            TextColumn::make('result_count')->label('处理结果')->formatStateUsing(fn ($state, $record): string => $record->status === 'done'
                ? ($record->type === 'groups' ? $state.' 个相似组' : $state.' 张重复图已归档') : '待完成'),
            TextColumn::make('last_photo_id')->label('已分析至图片 ID'),
            TextColumn::make('error_message')->label('失败原因')->wrap(),
            TextColumn::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i'),
        ])->defaultSort('id', 'desc')->poll('5s')->recordActions([
            Action::make('retry')->label('重新提交')->visible(fn ($record) => $record->status === 'failed')->requiresConfirmation()
                ->action(function ($record): void {
                    abort_unless(auth()->user()?->isAdministrator(), 403);
                    $record->update(['status' => 'pending', 'error_message' => null, 'operated_by' => auth()->id()]);
                    ProcessPhotoGovernance::dispatch($record->id);
                }),
        ])->emptyStateHeading('暂无相似图处理任务');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePhotoGovernanceRuns::route('/')];
    }
}
