<?php

namespace App\Filament\Resources\SubmissionApplications;

use App\Filament\Resources\SubmissionApplications\Pages\ManageSubmissionApplications;
use App\Models\SubmissionApplication;
use App\Models\SubmissionEvent;
use App\Services\AdminUserManagementService;
use App\Services\SubmissionQualification;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SubmissionApplicationResource extends Resource
{
    protected static ?string $model = SubmissionApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = '用户与权限';

    protected static ?string $navigationLabel = '投稿资格申请';

    protected static ?string $modelLabel = '投稿资格申请';

    protected static ?string $pluralModelLabel = '投稿资格申请';

    protected static ?int $navigationSort = 15;

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
        $actions = [Action::make('history')->label('申请历史')->modalSubmitAction(false)->modalCancelActionLabel('关闭')->modalContent(fn (SubmissionApplication $record) => view('filament.submission-application-history', ['events' => SubmissionEvent::query()->where('application_id', $record->id)->oldest()->get()]))];
        foreach (['approved' => '通过', 'rejected' => '拒绝', 'revoked' => '撤销资格'] as $decision => $label) {
            $actions[] = Action::make($decision)->label($label)->color($decision === 'approved' ? 'success' : 'danger')
                ->visible(fn (SubmissionApplication $record): bool => $decision === 'revoked' ? $record->status === 'approved' : $record->status === 'pending')
                ->form([Textarea::make('note')->label('用户可见处理原因')->required()->maxLength(2000)])
                ->action(fn (SubmissionApplication $record, array $data) => app(SubmissionQualification::class)->review($record, auth()->user(), $decision, $data['note']));
        }

        return $table->columns([
            TextColumn::make('user.name')->label('用户')->searchable(),
            TextColumn::make('user.email')->label('邮箱')->searchable(),
            TextColumn::make('user.role')->label('当前角色')->formatStateUsing(fn ($state) => AdminUserManagementService::ROLES[$state] ?? $state),
            TextColumn::make('reason')->label('申请理由')->wrap(),
            TextColumn::make('status')->label('状态')->badge()->formatStateUsing(fn ($state) => SubmissionApplication::STATUSES[$state] ?? $state),
            TextColumn::make('review_note')->label('处理原因')->wrap(),
            TextColumn::make('reviewer.name')->label('审核人'),
            TextColumn::make('created_at')->label('申请时间')->dateTime('Y-m-d H:i')->timezone('Asia/Shanghai'),
            TextColumn::make('reviewed_at')->label('处理时间')->dateTime('Y-m-d H:i')->timezone('Asia/Shanghai'),
        ])->filters([SelectFilter::make('status')->label('状态')->options(SubmissionApplication::STATUSES)])
            ->defaultSort('id', 'desc')->recordActions($actions);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSubmissionApplications::route('/')];
    }
}
