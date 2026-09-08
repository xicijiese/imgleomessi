<?php

namespace App\Filament\Resources\Badges;

use App\Filament\Resources\Badges\Pages\ManageBadges;
use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class BadgeResource extends Resource
{
    protected static ?string $model = Badge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = '社区与审核';

    protected static ?string $navigationLabel = '勋章 / 成就';

    protected static ?string $modelLabel = '勋章';

    protected static ?string $pluralModelLabel = '勋章 / 成就';

    protected static ?int $navigationSort = 40;

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
                    ->required(),
                TextInput::make('rule_threshold')
                    ->label('规则阈值')
                    ->numeric()
                    ->helperText('注册、支持者规则可填 1；人工发放可留空。'),
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
                    ->counts('userBadges')
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
                Action::make('grant')
                    ->label('发放')
                    ->color('success')
                    ->form([
                        Select::make('user_id')
                            ->label('用户')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        Textarea::make('note')
                            ->label('备注')
                            ->rows(3),
                    ])
                    ->action(function (Badge $record, array $data): void {
                        $operator = auth()->user() instanceof User ? auth()->user() : null;
                        $user = User::query()->findOrFail($data['user_id']);

                        app(BadgeService::class)->award($user, $record, 'manual', $operator, $data['note'] ?? null);

                        Notification::make()
                            ->title('勋章已发放')
                            ->success()
                            ->send();
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
                            ->label('备注')
                            ->rows(3),
                    ])
                    ->visible(fn (Badge $record): bool => $record->userBadges()->where('status', 'earned')->exists())
                    ->action(function (array $data): void {
                        $operator = auth()->user() instanceof User ? auth()->user() : null;
                        $userBadge = UserBadge::query()->findOrFail($data['user_badge_id']);

                        app(BadgeService::class)->revoke($userBadge, $operator, $data['note'] ?? null);

                        Notification::make()
                            ->title('勋章已撤销')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBadges::route('/'),
        ];
    }
}
