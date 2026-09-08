<?php

namespace App\Filament\Resources\Opponents;

use App\Filament\Resources\Opponents\Pages\ManageOpponents;
use App\Models\Opponent;
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
use Illuminate\Support\Str;
use UnitEnum;

class OpponentResource extends Resource
{
    protected static ?string $model = Opponent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = '图库字典';

    protected static ?string $navigationLabel = '对手管理';

    protected static ?string $modelLabel = '对手';

    protected static ?string $pluralModelLabel = '对手';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('对手名称')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, mixed $state, callable $set): void {
                        if ($operation === 'create' && filled($state)) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('country')
                    ->label('国家 / 地区')
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Select::make('is_active')
                    ->label('状态')
                    ->options([
                        1 => '启用',
                        0 => '停用',
                    ])
                    ->default(1)
                    ->required(),
                Textarea::make('aliases')
                    ->label('别名')
                    ->rows(2)
                    ->helperText('可填写简称、英文名或常见别名，仅用于后台整理和前台搜索。')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('说明')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('对手名称')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('country')
                    ->label('国家 / 地区')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('photos_count')
                    ->label('关联图片')
                    ->counts('photos')
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
                SelectFilter::make('is_active')
                    ->label('状态')
                    ->options([
                        1 => '启用',
                        0 => '停用',
                    ]),
            ])
            ->defaultSort('sort_order')
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
            'index' => ManageOpponents::route('/'),
        ];
    }
}