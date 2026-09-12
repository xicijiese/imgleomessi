<?php

namespace App\Filament\Resources\UserLoginHistories;

use App\Filament\Resources\UserLoginHistories\Pages\ManageUserLoginHistories;
use App\Models\UserLoginHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserLoginHistoryResource extends Resource
{
    protected static ?string $model = UserLoginHistory::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    protected static string|UnitEnum|null $navigationGroup = '用户与权限';
    protected static ?string $navigationLabel = '登录历史';
    protected static ?string $modelLabel = '登录记录';
    protected static ?string $pluralModelLabel = '登录历史';
    protected static ?int $navigationSort = 90;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('logged_in_at')->label('登录时间')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('user.name')->label('用户')->searchable(),
                TextColumn::make('user.email')->label('邮箱')->searchable(),
                TextColumn::make('ip_address')->label('IP')->placeholder('未知'),
                TextColumn::make('user_agent')->label('客户端')->limit(80)->tooltip(fn (?string $state): ?string => $state),
            ])
            ->defaultSort('logged_in_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageUserLoginHistories::route('/')];
    }
}