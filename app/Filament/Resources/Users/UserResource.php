<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\AdminUserManagementService;
use App\Services\ContributorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;
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
                self::avatarUpload(),
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
                ImageColumn::make('avatar')
                    ->label('头像')
                    ->circular(),
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
                TextColumn::make('last_login_at')
                    ->label('最近登录')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('从未登录')
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
                SelectFilter::make('role')
                    ->label('角色')
                    ->options(AdminUserManagementService::ROLES),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('enableSelected')
                        ->label('批量启用')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, AdminUserManagementService $service): void {
                            $operator = auth()->user();
                            abort_unless($operator instanceof User, 403);
                            $count = $records->filter(fn (User $record): bool => ! $record->is($operator) && $service->enable($record, $operator))->count();
                            Notification::make()->title('已启用 '.$count.' 个用户')->success()->send();
                        }),
                    BulkAction::make('disableSelected')
                        ->label('批量停用')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, AdminUserManagementService $service): void {
                            $operator = auth()->user();
                            abort_unless($operator instanceof User, 403);
                            $count = $records->filter(fn (User $record): bool => ! $record->is($operator) && $service->disable($record, $operator))->count();
                            Notification::make()->title('已停用 '.$count.' 个用户')->success()->send();
                        }),
                    BulkAction::make('banSelected')
                        ->label('批量封禁')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, AdminUserManagementService $service): void {
                            $operator = auth()->user();
                            abort_unless($operator instanceof User, 403);
                            $count = $records->filter(fn (User $record): bool => ! $record->is($operator) && $service->ban($record, $operator))->count();
                            Notification::make()->title('已封禁 '.$count.' 个用户')->success()->send();
                        }),
                    BulkAction::make('unbanSelected')
                        ->label('批量解封')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, AdminUserManagementService $service): void {
                            $operator = auth()->user();
                            abort_unless($operator instanceof User, 403);
                            $count = $records->filter(fn (User $record): bool => ! $record->is($operator) && $service->unban($record, $operator))->count();
                            Notification::make()->title('已解封 '.$count.' 个用户')->success()->send();
                        }),
                ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (User $record, array $data): User {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);
                        $audit = app(AdminAuditService::class);
                        $before = $audit->userState($record);
                        $record->update($data);
                        $audit->record($operator, 'user.profile_updated', $record, $before, $audit->userState($record));

                        return $record;
                    }),
                Action::make('changeRole')
                    ->label('调整角色')
                    ->color('warning')
                    ->visible(fn (User $record): bool => ! $record->is(auth()->user()))
                    ->requiresConfirmation()
                    ->form([
                        Select::make('role')
                            ->label('角色')
                            ->options(AdminUserManagementService::ROLES)
                            ->required(),
                    ])
                    ->fillForm(fn (User $record): array => ['role' => $record->role])
                    ->action(function (User $record, array $data, AdminUserManagementService $service): void {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);
                        $service->changeRole($record, $operator, $data['role']);
                        Notification::make()->title('用户角色已更新')->success()->send();
                    }),
                Action::make('enable')
                    ->label('启用')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->status === 'disabled')
                    ->action(function (User $record, AdminUserManagementService $service): void {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);
                        $service->enable($record, $operator);
                        Notification::make()->title('用户已启用')->success()->send();
                    }),
                Action::make('disable')
                    ->label('停用')
                    ->color('warning')
                    ->visible(fn (User $record): bool => $record->status === 'active' && $record->role !== 'admin')
                    ->requiresConfirmation()
                    ->action(function (User $record, AdminUserManagementService $service): void {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);
                        $service->disable($record, $operator);
                        Notification::make()->title('用户已停用')->success()->send();
                    }),
                Action::make('resetPassword')
                    ->label('发送重置密码')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (User $record, AdminUserManagementService $service): void {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);
                        $service->sendPasswordReset($record, $operator);
                        Notification::make()->title('密码重置邮件已发送')->success()->send();
                    }),
                Action::make('revokeSessions')
                    ->label('强制退出全部会话')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record, AdminUserManagementService $service): void {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);
                        $service->revokeSessions($record, $operator);
                        Notification::make()->title('该用户的全部会话已失效')->success()->send();
                    }),

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
                    ->action(function (User $record, array $data, AdminUserManagementService $service): bool {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);
                        return $service->ban($record, $operator, $data['ban_reason'] ?? null, $data['moderation_note'] ?? null, $data['banned_until'] ?? null);
                    }),
                Action::make('unban')
                    ->label('解封')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->isBanned())
                    ->form([
                        Textarea::make('moderation_note')
                            ->label('内部备注')
                            ->rows(3),
                    ])
                    ->action(function (User $record, array $data, AdminUserManagementService $service): bool {
                        $operator = auth()->user();
                        abort_unless($operator instanceof User, 403);
                        return $service->unban($record, $operator, $data['moderation_note'] ?? null);
                    }),
            ]);
    }

    private static function avatarUpload(): FileUpload
    {
        return FileUpload::make('avatar_url')
            ->label('头像')
            ->image()
            ->disk('public')
            ->visibility('public')
            ->directory('avatars')
            ->maxSize(2048)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->fetchFileInformation(false)
            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                $filename = Str::uuid().'.'.$extension;
                $path = app(\App\Services\PhotoStorage::class)->diskForKey()->putFileAs(
                    $component->getDirectory(),
                    $file,
                    $filename,
                    ['visibility' => 'public'],
                );

                return is_string($path) ? $path : null;
            })
            ->getUploadedFileUsing(function (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                $storage = app(\App\Services\PhotoStorage::class);
                $disk = $storage->diskForKey($file);

                try {
                    if (! $disk->exists($file)) {
                        return null;
                    }

                    $name = is_array($storedFileNames) ? ($storedFileNames[$file] ?? null) : $storedFileNames;

                    return [
                        'name' => $name ?? basename($file),
                        'size' => (int) $disk->size($file),
                        'type' => $disk->mimeType($file),
                        'url' => Str::sanitizeUrl($storage->url($file)),
                    ];
                } catch (Throwable) {
                    return null;
                }
            })
            ->deleteUploadedFileUsing(function (string|TemporaryUploadedFile $file): void {
                if ($file instanceof TemporaryUploadedFile) {
                    return;
                }

                app(\App\Services\PhotoStorage::class)->diskForKey($file)->delete($file);
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
