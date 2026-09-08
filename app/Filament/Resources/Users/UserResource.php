<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                TextColumn::make('status')
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
