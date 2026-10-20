<?php

namespace App\Filament\Resources\Badges;

use App\Filament\Resources\Badges\Pages\ManageBadges;
use App\Models\Badge;
use App\Models\BadgeEvent;
use App\Models\BadgeGrantBatch;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeGrantService;
use App\Services\BadgeService;
use App\Services\PhotoStorage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class BadgeResource extends Resource
{
    protected static ?string $model = Badge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = '社区与审核';

    protected static ?string $navigationLabel = '勋章运营';

    protected static ?string $modelLabel = '勋章';

    protected static ?string $pluralModelLabel = '勋章运营';

    protected static ?int $navigationSort = 40;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && ! $record->userBadges()->exists()
            && ! BadgeGrantBatch::query()->where('badge_id', $record->id)->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('勋章名称')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('标识')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->label('说明')
                    ->rows(3)
                    ->columnSpanFull(),
                FileUpload::make('image_path')->label('勋章图片')->image()
                    ->acceptedFileTypes(['image/png', 'image/webp', 'image/gif'])
                    ->maxSize(2048)->disk('public')->visibility('public')->directory('badges')
                    ->fetchFileInformation(false)
                    ->helperText('支持 PNG、WebP、GIF，最大 2MB。GIF 原样保存，不裁剪、不压缩。未上传时沿用图标。')
                    ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): string {
                        return app(PhotoStorage::class)->diskForKey()->putFileAs('badges', $file, $component->getUploadedFileNameForStorage($file), ['visibility' => 'public']);
                    })
                    ->getUploadedFileUsing(fn (string $file): array => ['name' => basename($file), 'size' => 0, 'type' => null, 'url' => app(PhotoStorage::class)->url($file)]),
                TextInput::make('icon_key')
                    ->label('图标标识')
                    ->maxLength(255),
                TextInput::make('color')
                    ->label('颜色')
                    ->default('#3da9fc')
                    ->maxLength(24),
                Select::make('rule_type')
                    ->label('获得规则')
                    ->options(Badge::RULE_TYPES)
                    ->default('manual')
                    ->live()
                    ->required(),
                DatePicker::make('starts_on')->label('开始日期（北京时间）')->native(false)
                    ->visible(fn (Get $get): bool => $get('rule_type') === 'anniversary')
                    ->required(fn (Get $get): bool => $get('rule_type') === 'anniversary'),
                DatePicker::make('ends_on')->label('结束日期（含当天）')->native(false)->afterOrEqual('starts_on')
                    ->visible(fn (Get $get): bool => $get('rule_type') === 'anniversary')
                    ->required(fn (Get $get): bool => $get('rule_type') === 'anniversary'),
                TextInput::make('rule_threshold')
                    ->label('规则阈值')
                    ->integer()->minValue(1)
                    ->visible(fn (Get $get): bool => in_array($get('rule_type'), ['favorites_count', 'comments_count'], true))
                    ->required(fn (Get $get): bool => in_array($get('rule_type'), ['favorites_count', 'comments_count'], true))
                    ->helperText('仅沿用已有基础规则；投稿、推广固定为 50，待对应功能开放后接通。'),
                TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('启用')
                    ->default(true),
                Textarea::make('internal_note')
                    ->label('内部备注')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')->label('图片')->square()->imageSize(48),
                TextColumn::make('name')
                    ->label('勋章名称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rule_type')
                    ->label('规则')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Badge::RULE_TYPES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('rule_threshold')
                    ->label('阈值')
                    ->placeholder('无')
                    ->sortable(),
                TextColumn::make('user_badges_count')
                    ->label('获得人数')
                    ->counts(['userBadges' => fn ($query) => $query->where('status', 'earned')])
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('排序')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('rule_type')
                    ->label('规则')
                    ->options(Badge::RULE_TYPES),
                TernaryFilter::make('is_active')
                    ->label('启用状态')
                    ->trueLabel('已启用')
                    ->falseLabel('已停用')
                    ->native(false),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                Action::make('grant')->label('批量发放')->color('success')
                    ->visible(fn (Badge $record): bool => $record->is_active)
                    ->modalHeading('确认发放勋章')
                    ->modalDescription('将为本次提交时状态正常的用户创建发放任务。已获得或已撤销的勋章会跳过；不会自动佩戴，不影响用户权限。')
                    ->modalSubmitActionLabel('确认并提交发放')
                    ->form([
                        Select::make('audience')->label('发放范围')->options(['selected' => '指定用户', 'all' => '全体正常用户'])->default('selected')->live()->required(),
                        Select::make('user_ids')->label('指定用户')->multiple()->searchable()->live()
                            ->getSearchResultsUsing(fn (string $search): array => User::query()->where('status', 'active')->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))->limit(50)->get()->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' <'.$user->email.'>'])->all())
                            ->getOptionLabelsUsing(fn (array $values): array => User::query()->whereIn('id', $values)->get()->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' <'.$user->email.'>'])->all())
                            ->visible(fn (Get $get): bool => $get('audience') === 'selected')
                            ->required(fn (Get $get): bool => $get('audience') === 'selected'),
                        Text::make(fn (Get $get): string => '本次范围共 '.app(BadgeGrantService::class)->audience($get('audience') ?? 'selected', $get('user_ids') ?? [])->count().' 位正常用户（已持有或已撤销者自动跳过）。'),
                        Textarea::make('note')->label('发放说明')->required()->maxLength(1000),
                    ])
                    ->action(function (Badge $record, array $data): void {
                        app(BadgeGrantService::class)->create($record, auth()->user(), $data['audience'], $data['user_ids'] ?? [], $data['note']);
                        Notification::make()->title('任务已提交，请查看发放进度')->success()->send();
                    }),
                Action::make('revoke')
                    ->label('撤销')
                    ->color('danger')
                    ->form([
                        Select::make('user_badge_id')
                            ->label('用户勋章')
                            ->options(fn (Badge $record): array => UserBadge::query()
                                ->where('badge_id', $record->id)
                                ->where('status', 'earned')
                                ->with('user')
                                ->get()
                                ->mapWithKeys(fn (UserBadge $userBadge): array => [
                                    $userBadge->id => ($userBadge->user?->name ?? '未知用户').' #'.$userBadge->id,
                                ])
                                ->all())
                            ->searchable()
                            ->required(),
                        Textarea::make('note')
                            ->label('撤销原因')->required()->maxLength(1000)
                            ->rows(3),
                    ])
                    ->visible(fn (Badge $record): bool => $record->userBadges()->where('status', 'earned')->exists())
                    ->action(function (Badge $record, array $data): void {
                        $operator = auth()->user() instanceof User ? auth()->user() : null;
                        $userBadge = $record->userBadges()->where('status', 'earned')->findOrFail($data['user_badge_id']);

                        app(BadgeService::class)->revoke($userBadge, $operator, $data['note'] ?? null);

                        Notification::make()
                            ->title('勋章已撤销')
                            ->success()
                            ->send();
                    }),
                Action::make('restore')->label('恢复')->color('warning')
                    ->visible(fn (Badge $record): bool => $record->userBadges()->where('status', 'revoked')->exists())
                    ->form([
                        Select::make('user_badge_id')->label('已撤销记录')->searchable()->required()
                            ->options(fn (Badge $record): array => $record->userBadges()->where('status', 'revoked')->with('user')->get()->mapWithKeys(fn (UserBadge $item): array => [$item->id => ($item->user?->name ?? '未知用户').' #'.$item->user_id])->all()),
                        Textarea::make('note')->label('恢复原因')->required()->maxLength(1000),
                    ])
                    ->action(function (Badge $record, array $data): void {
                        app(BadgeService::class)->restore($record->userBadges()->where('status', 'revoked')->findOrFail($data['user_badge_id']), auth()->user(), $data['note']);
                        Notification::make()->title('勋章已恢复，用户可自行佩戴')->success()->send();
                    }),
                DeleteAction::make()->visible(fn (Badge $record): bool => static::canDelete($record)),
            ]);
    }

    public static function batchesTable(Table $table): Table
    {
        return $table->query(BadgeGrantBatch::query()->with(['badge', 'operator'])->withCount([
            'recipients', 'recipients as awarded_count' => fn ($query) => $query->where('status', 'awarded'),
            'recipients as skipped_count' => fn ($query) => $query->where('status', 'skipped'),
        ]))->columns([
            TextColumn::make('id')->label('批次'),
            TextColumn::make('badge.name')->label('勋章'),
            TextColumn::make('audience')->label('范围')->formatStateUsing(fn ($state) => $state === 'all' ? '全体正常用户' : '指定用户'),
            TextColumn::make('status')->label('状态')->badge()->formatStateUsing(fn ($state) => BadgeGrantBatch::STATUSES[$state] ?? $state),
            TextColumn::make('recipients_count')->label('快照人数'),
            TextColumn::make('awarded_count')->label('已发放'),
            TextColumn::make('skipped_count')->label('已跳过'),
            TextColumn::make('operator.name')->label('提交人'),
            TextColumn::make('note')->label('说明')->wrap(),
            TextColumn::make('error')->label('错误')->wrap(),
            TextColumn::make('created_at')->label('提交时间')->dateTime('Y-m-d H:i'),
        ])->filters([])->defaultSort('id', 'desc')->poll('5s')->recordActions([
            Action::make('retry')->label('重试未完成部分')->requiresConfirmation()->visible(fn (BadgeGrantBatch $record): bool => $record->status === 'failed')
                ->action(fn (BadgeGrantBatch $record) => app(BadgeGrantService::class)->retry($record, auth()->user())),
        ])->emptyStateHeading('暂无发放任务');
    }

    public static function eventsTable(Table $table): Table
    {
        return $table->query(BadgeEvent::query()->with(['userBadge.user', 'userBadge.badge', 'operator']))->columns([
            TextColumn::make('userBadge.user.name')->label('用户')->searchable(),
            TextColumn::make('userBadge.badge.name')->label('勋章')->searchable(),
            TextColumn::make('action')->label('操作')->formatStateUsing(fn ($state) => BadgeEvent::ACTIONS[$state] ?? $state),
            TextColumn::make('operator.name')->label('操作者')->placeholder('自动规则'),
            TextColumn::make('note')->label('原因')->wrap(),
            TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i:s'),
        ])->filters([])->defaultSort('id', 'desc')->recordActions([])->emptyStateHeading('暂无勋章操作记录');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBadges::route('/'),
        ];
    }
}
