<?php

namespace App\Filament\Resources\SensitiveWords;

use App\Filament\Resources\SensitiveWords\Pages\ManageSensitiveWords;
use App\Models\SensitiveWord;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SensitiveWordResource extends Resource
{
    protected static ?string $model = SensitiveWord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = '社区与审核';

    protected static ?string $navigationLabel = '敏感词规则';

    protected static ?string $modelLabel = '敏感词';

    protected static ?string $pluralModelLabel = '敏感词';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('word')
                    ->label('词条')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('severity')
                    ->label('风险级别')
                    ->options(SensitiveWord::SEVERITIES)
                    ->default('medium')
                    ->required(),
                Select::make('is_enabled')
                    ->label('是否启用')
                    ->options([true => '启用', false => '停用'])
                    ->default(true)
                    ->required(),
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
                TextColumn::make('word')
                    ->label('词条')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('severity')
                    ->label('风险级别')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SensitiveWord::SEVERITIES[$state] ?? $state)
                    ->sortable(),
                IconColumn::make('is_enabled')
                    ->label('启用')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->label('风险级别')
                    ->options(SensitiveWord::SEVERITIES),
                SelectFilter::make('is_enabled')
                    ->label('启用状态')
                    ->options([true => '启用', false => '停用']),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSensitiveWords::route('/'),
        ];
    }
}
