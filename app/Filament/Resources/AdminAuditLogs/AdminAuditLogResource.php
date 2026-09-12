<?php

namespace App\Filament\Resources\AdminAuditLogs;

use App\Filament\Resources\AdminAuditLogs\Pages\ManageAdminAuditLogs;
use App\Models\AdminAuditLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;
use UnitEnum;

class AdminAuditLogResource extends Resource
{
    protected static ?string $model = AdminAuditLog::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static string|UnitEnum|null $navigationGroup = '用户与权限';
    protected static ?string $navigationLabel = '管理员操作审计';
    protected static ?string $modelLabel = '审计记录';
    protected static ?string $pluralModelLabel = '管理员操作审计';
    protected static ?int $navigationSort = 80;

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
        return parent::getEloquentQuery()->with(['actor', 'target']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('actor.name')->label('操作者')->placeholder('系统'),
                TextColumn::make('target.name')->label('目标用户')->placeholder('无'),
                TextColumn::make('action')->label('动作')->badge()->searchable(),
                TextColumn::make('ip_address')->label('IP')->placeholder('未知'),
                TextColumn::make('after_state')->label('结果')->formatStateUsing(fn (?array $state): string => $state ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—')->wrap(),
            ])
            ->filters([
                SelectFilter::make('action')->label('动作')->options(fn (): array => AdminAuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageAdminAuditLogs::route('/')];
    }
}