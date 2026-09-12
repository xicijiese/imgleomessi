<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Services\ContributorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = '用户与权限';

    protected static ?string $navigationLabel = '用户管理';

    protected static ?string $modelLabel = '用户';

    protected static ?string $pluralModelLabel = '用户';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('contributorProfile');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('昵称')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('邮箱')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('status')
                    ->label('用户状态')
                    ->options(User::STATUSES)
                    ->default('active')
                    ->required(),
                DateTimePicker::make('banned_until')
                    ->label('封禁到期')
                    ->seconds(false),
                Textarea::make('ban_reason')
                    ->label('封禁原因')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('moderation_note')
                    ->label('内部备注')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('昵称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('邮箱')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('角色')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => [
                        'user' => '普通用户',
                        'editor' => '编辑员',
                        'admin' => '管理员',
                    ][$state] ?? '未知')
                    ->sortable(),
                TextColumn::make('contributorProfile.display_name')
                    ->label('档案共建者')
                    ->placeholder('未授予')
                    ->badge(),
                TextColumn::make('contributorProfile.is_public')
                    ->label('公开展示')
                    ->badge()
                    ->formatStateUsing(fn (?bool $state, User $record): string => ! $record->contributorProfile
                        ? '未授予'
                        : ($state ? '公开' : '不公开')),                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => User::STATUSES[$state] ?? '正常')
                    ->sortable(),
                TextColumn::make('banned_until')
                    ->label('封禁到期')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('未封禁')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('注册时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(User::STATUSES),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('grantContributor')
                    ->label('授予档案共建者')
                    ->color('primary')
                    ->visible(fn (User $record): bool => $record->role !== 'admin' && ! $record->contributorProfile?->isActive())
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('display_name')
                            ->label('展示名称')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('bio')
                            ->label('公开简介')
                            ->rows(3)
                            ->maxLength(1000),
                        TextInput::make('contribution_focus')
                            ->label('贡献方向')
                            ->maxLength(255),
                        TextInput::make('sort_order')
                            ->label('展示顺序')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_public')
                            ->label('公开展示')
                            ->default(false),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'display_name' => $record->name,
                        'bio' => null,
                        'contribution_focus' => null,
                        'sort_order' => 0,
                        'is_public' => false,
                    ])
                    ->action(function (User $record, array $data): void {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);

                        app(ContributorService::class)->grant($record, $operator, $data);

                        Notification::make()
                            ->success()
                            ->title('档案共建者已授予')
                            ->body('该用户已获得图片档案整理权限。')
                            ->send();
                    }),
                Action::make('editContributor')
                    ->label('编辑共建者资料')
                    ->color('warning')
                    ->visible(fn (User $record): bool => $record->contributorProfile?->isActive() === true)
                    ->form([
                        TextInput::make('display_name')
                            ->label('展示名称')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('bio')
                            ->label('公开简介')
                            ->rows(3)
                            ->maxLength(1000),
                        TextInput::make('contribution_focus')
                            ->label('贡献方向')
                            ->maxLength(255),
                        TextInput::make('sort_order')
                            ->label('展示顺序')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_public')
                            ->label('公开展示'),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'display_name' => $record->contributorProfile?->display_name ?: $record->name,
                        'bio' => $record->contributorProfile?->bio,
                        'contribution_focus' => $record->contributorProfile?->contribution_focus,
                        'sort_order' => $record->contributorProfile?->sort_order ?? 0,
                        'is_public' => (bool) ($record->contributorProfile?->is_public),
                    ])
                    ->action(function (User $record, array $data): void {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);

                        app(ContributorService::class)->updateProfile($record, $operator, $data);

                        Notification::make()
                            ->success()
                            ->title('共建者资料已保存')
                            ->send();
                    }),
                Action::make('revokeContributor')
                    ->label('撤销共建者')
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->role !== 'admin' && $record->contributorProfile?->isActive() === true)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);

                        app(ContributorService::class)->revoke($record, $operator);

                        Notification::make()
                            ->success()
                            ->title('档案共建者已撤销')
                            ->body('该用户的共建者资料已停用，若编辑员身份由本次授予产生，也已恢复为普通用户。')
                            ->send();
                    }),
                Action::make('ban')
                    ->label('封禁')
                    ->color('danger')
                    ->visible(fn (User $record): bool => ! $record->isBanned())
                    ->form([
                        DateTimePicker::make('banned_until')
                            ->label('封禁到期')
                            ->seconds(false),
                        Textarea::make('ban_reason')
                            ->label('封禁原因')
                            ->rows(3),
                        Textarea::make('moderation_note')
                            ->label('内部备注')
                            ->rows(3),
                    ])
                    ->action(fn (User $record, array $data): bool => $record->ban($data['ban_reason'] ?? null, $data['moderation_note'] ?? null, $data['banned_until'] ?? null)),
                Action::make('unban')
                    ->label('解封')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->isBanned())
                    ->form([
                        Textarea::make('moderation_note')
                            ->label('内部备注')
                            ->rows(3),
                    ])
                    ->action(fn (User $record, array $data): bool => $record->unban($data['moderation_note'] ?? null)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
