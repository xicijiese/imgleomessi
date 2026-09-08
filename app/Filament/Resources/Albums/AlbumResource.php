<?php

namespace App\Filament\Resources\Albums;

use App\Filament\Resources\Albums\Pages\ManageAlbums;
use App\Models\Album;
use App\Models\Category;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class AlbumResource extends Resource
{
    protected static ?string $model = Album::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = '图库管理';

    protected static ?string $navigationLabel = '相册管理';

    protected static ?string $modelLabel = '相册';

    protected static ?string $pluralModelLabel = '相册';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('相册名')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('URL 标识')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::slug($state) : null),
                Select::make('categories')
                    ->label('相册分类')
                    ->relationship(
                        name: 'categories',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->children()
                            ->with('parent')
                            ->orderBy('parent_id')
                            ->orderBy('sort_order')
                            ->orderBy('id'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Category $record): string => ($record->parent?->name ?? '未分组').' / '.$record->name)
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required()
                    ->rules([self::completeCategorySetRule()])
                    ->helperText('必须从 7 个固定主分类中各选择 1 个子分类。'),
                Select::make('status')
                    ->label('状态')
                    ->options(Album::STATUSES)
                    ->default('draft')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0)
                    ->required(),
                DateTimePicker::make('published_at')
                    ->label('发布时间')
                    ->seconds(false),
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
                TextColumn::make('title')
                    ->label('相册名')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('URL 标识')
                    ->searchable(),
                TextColumn::make('categories_count')
                    ->label('分类数')
                    ->counts('categories'),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Album::STATUSES[$state] ?? $state),
                TextColumn::make('sort_order')
                    ->label('排序')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(Album::STATUSES),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAlbums::route('/'),
        ];
    }

    private static function completeCategorySetRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $categoryIds = collect($value)->filter()->map(fn (mixed $id): int => (int) $id)->unique();
            $rootCount = Category::query()->roots()->count();

            if ($rootCount === 0 || $categoryIds->count() !== $rootCount) {
                $fail('相册必须从每个固定主分类下各选择 1 个子分类。');

                return;
            }

            $selectedParentCount = Category::query()
                ->whereIn('id', $categoryIds)
                ->children()
                ->distinct()
                ->count('parent_id');

            if ($selectedParentCount !== $rootCount) {
                $fail('相册必须从每个固定主分类下各选择 1 个子分类。');
            }
        };
    }
}
