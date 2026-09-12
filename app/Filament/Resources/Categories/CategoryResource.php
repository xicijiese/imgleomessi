<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = '图库字典';

    protected static ?string $navigationLabel = '子分类管理';

    protected static ?string $modelLabel = '子分类';

    protected static ?string $pluralModelLabel = '子分类';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label('所属主分类')
                    ->options(fn (): array => Category::query()
                        ->roots()
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('选择所属主分类后，仅维护该主分类下的子分类；必选主分类用 * 标记。'),
                TextInput::make('name')
                    ->label('子分类名称')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('URL 标识')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::slug($state) : null),
                Select::make('visibility')
                    ->label('可见性')
                    ->options(Category::VISIBILITIES)
                    ->default('public')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Textarea::make('description')
                    ->label('说明')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('parent.name')
                    ->label('主分类')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('子分类')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('URL 标识')
                    ->searchable(),
                TextColumn::make('visibility')
                    ->label('可见性')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Category::VISIBILITIES[$state] ?? $state),
                TextColumn::make('sort_order')
                    ->label('排序')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->default(fn (): ?int => Category::query()->roots()->orderBy('sort_order')->value('id'))
                    ->label('主分类')
                    ->options(fn (): array => Category::query()
                        ->roots()
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all()),
                SelectFilter::make('visibility')
                    ->label('可见性')
                    ->options(Category::VISIBILITIES),
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Category $record): bool => $record->is_system)
                    ->before(function (DeleteAction $action, Category $record): void {
                        if ($reason = $record->deletionBlockReason()) {
                            Notification::make()
                                ->danger()
                                ->title('无法删除分类')
                                ->body($reason)
                                ->send();

                            $action->cancel();
                        }
                    })
                    ->action(function (DeleteAction $action, Category $record): void {
                        try {
                            $record->delete();
                        } catch (QueryException $exception) {
                            report($exception);

                            Notification::make()
                                ->danger()
                                ->title('无法删除分类')
                                ->body('该分类刚刚产生了新的关联，请刷新页面后检查；也可以将分类设置为隐藏。')
                                ->send();

                            $action->cancel(true);
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->children()->with('parent');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
