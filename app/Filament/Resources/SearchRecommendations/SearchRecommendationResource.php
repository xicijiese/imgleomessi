<?php

namespace App\Filament\Resources\SearchRecommendations;

use App\Filament\Resources\SearchRecommendations\Pages\ManageSearchRecommendations;
use App\Models\SearchRecommendation;
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

class SearchRecommendationResource extends Resource
{
    protected static ?string $model = SearchRecommendation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = '图库管理';

    protected static ?string $navigationLabel = '搜索运营';

    protected static ?string $modelLabel = '推荐搜索词';

    protected static ?string $pluralModelLabel = '推荐搜索词';

    protected static ?int $navigationSort = 80;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('keyword')
                    ->label('搜索词')
                    ->required()
                    ->maxLength(120),
                TextInput::make('title')
                    ->label('展示标题')
                    ->required()
                    ->maxLength(120),
                TextInput::make('url')
                    ->label('跳转 URL')
                    ->maxLength(255)
                    ->helperText('为空时默认跳转到 /search?q=搜索词；只建议填写站内路径。'),
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
                Textarea::make('description')
                    ->label('前台说明')
                    ->rows(3)
                    ->columnSpanFull(),
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
                TextColumn::make('title')
                    ->label('展示标题')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('keyword')
                    ->label('搜索词')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('url')
                    ->label('跳转 URL')
                    ->toggleable(),
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
            'index' => ManageSearchRecommendations::route('/'),
        ];
    }
}
