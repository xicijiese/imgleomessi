<?php

namespace App\Filament\Resources\Sources;

use App\Filament\Resources\Sources\Pages\ManageSources;
use App\Models\Source;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = '图库管理';

    protected static ?string $navigationLabel = '来源管理';

    protected static ?string $modelLabel = '来源';

    protected static ?string $pluralModelLabel = '来源';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('original_url')
                    ->label('原始链接')
                    ->url()
                    ->maxLength(2048),
                DateTimePicker::make('published_at')
                    ->label('原始发布日期')
                    ->seconds(false),
                Toggle::make('is_enabled')
                    ->label('启用')
                    ->default(true),
                Textarea::make('copyright_note')
                    ->label('版权备注')
                    ->rows(4)
                    ->columnSpanFull(),
                Textarea::make('internal_note')
                    ->label('内部备注')
                    ->helperText('只在后台可见，不在前台展示。')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_url')
                    ->label('原始链接')
                    ->limit(60)
                    ->searchable()
                    ->placeholder('未填写'),
                TextColumn::make('published_at')
                    ->label('原始发布日期')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('未填写'),
                IconColumn::make('is_enabled')
                    ->label('启用')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_enabled')
                    ->label('启用状态')
                    ->trueLabel('已启用')
                    ->falseLabel('已禁用')
                    ->native(false),
                Filter::make('has_original_url')
                    ->label('有原始链接')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('original_url')->where('original_url', '!=', '')),
                Filter::make('has_copyright_note')
                    ->label('有版权备注')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('copyright_note')->where('copyright_note', '!=', '')),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('toggleEnabled')
                    ->label(fn (Source $record): string => $record->is_enabled ? '禁用' : '启用')
                    ->color(fn (Source $record): string => $record->is_enabled ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Source $record): bool => $record->update(['is_enabled' => ! $record->is_enabled])),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSources::route('/'),
        ];
    }
}
