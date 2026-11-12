<?php

namespace App\Filament\Resources\Albums;

use App\Filament\Forms\CategoryOption;
use App\Filament\Resources\Albums\Pages\ManageAlbums;
use App\Models\Album;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
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
                Section::make('基础资料')->schema([
                    TextInput::make('title')
                        ->label('相册名')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?Album $record, ?string $state, Set $set): void {
                            if ($record === null) {
                                $set('slug', self::suggestSlug($state));
                            }
                        })
                        ->required()
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->label('URL 标识')
                        ->helperText('新建时根据相册名自动生成无声调拼音，可手动调整；编辑名称不会改变已有链接。')
                        ->required(fn (?Album $record): bool => $record !== null)
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (?string $state, Get $get): string => filled($state) ? Str::slug($state) : self::suggestSlug($get('title'))),
                ])->columns(2)->columnSpanFull(),
                Section::make('相册分类')
                    ->schema(self::categorySelectors())
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('发布设置')->schema([
                    Select::make('status')
                        ->label('状态')
                        ->options(Album::STATUSES)
                        ->default('published')
                        ->required(),
                    Hidden::make('sort_order')->default(0)->rules(['integer', 'min:0']),
                    DateTimePicker::make('published_at')
                        ->label('发布时间')
                        ->seconds(false),
                ])->columns(3)->columnSpanFull(),
                Textarea::make('description')
                    ->label('说明')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function suggestSlug(?string $title): string
    {
        $base = Str::slug(Str::transliterate($title ?? ''));
        $base = $base === '' ? 'album-'.Str::lower(Str::random(8)) : rtrim(substr($base, 0, 240), '-');
        $slug = $base;
        $suffix = 2;
        while (Album::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'admin-content-table'])
            ->columns([
                TextColumn::make('title')->wrap()->extraCellAttributes(['class' => 'admin-object-cell'])
                    ->label('相册名')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('URL 标识')
                    ->searchable(),
                TextColumn::make('categories_count')->alignEnd()
                    ->label('分类数')
                    ->counts('categories'),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Album::STATUSES[$state] ?? $state),
                TextColumn::make('is_featured')
                    ->label('首页精选')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '已精选' : '未精选')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                TextColumn::make('sort_order')->alignEnd()
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
            ->defaultSort(fn (Builder $query) => $query->orderBy('sort_order')->orderByDesc('created_at')->orderByDesc('id'))
            ->reorderable('sort_order')
            ->reorderRecordsTriggerAction(fn (Action $action) => $action->label(fn ($livewire) => $livewire->isTableReordering ? '完成排序' : '调整排序'))
            ->recordActions([
                Action::make('toggleFeatured')
                    ->label(fn (Album $record): string => $record->is_featured ? '取消精选' : '精选首页')
                    ->color(fn (Album $record): string => $record->is_featured ? 'gray' : 'warning')
                    ->action(function (Album $record): void {
                        if (! $record->is_featured) {
                            $hasPublicPhoto = $record->photos()
                                ->where('photos.status', 'published')
                                ->whereNotIn('photos.copyright_status', ['restricted', 'remove_requested'])
                                ->exists();

                            if ($record->status !== 'published' || ! $hasPublicPhoto) {
                                Notification::make()
                                    ->title('相册暂不能设为首页精选')
                                    ->body('请先发布相册，并确保相册内有可公开展示的图片。')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $record->forceFill([
                                'is_featured' => true,
                                'featured_at' => now(),
                            ])->save();

                            Notification::make()->title('已推荐到首页精选相册')->success()->send();

                            return;
                        }

                        $record->forceFill([
                            'is_featured' => false,
                            'featured_at' => null,
                        ])->save();

                        Notification::make()->title('已取消首页精选')->success()->send();
                    }),
                EditAction::make()
                    ->modalWidth('5xl')
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->mutateRecordDataUsing(fn (array $data, Album $record): array => [
                        ...$data,
                        ...self::categoryFormDataFromRecord($record),
                    ])
                    ->using(function (array $data, Album $record): void {
                        $categoryIds = self::categoryIdsFromFormData($data);
                        $record->update(Arr::except($data, self::categoryFieldNames()));
                        $record->categories()->sync($categoryIds);
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAlbums::route('/'),
        ];
    }

    /**
     * 为每个主分类提供独立的子分类选择器，避免所有子分类混在一个下拉中。
     *
     * @return array<int, Select>
     */
    private static function categorySelectors(): array
    {
        return Category::query()
            ->roots()
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Category $root): Select {
                return CategoryOption::allowCreation(Select::make(self::categoryFieldName((int) $root->id)), $root)
                    ->label($root->name)
                    ->multiple(in_array($root->slug, Category::MULTI_SELECT_ROOT_SLUGS, true))
                    ->options(fn (): array => CategoryOption::options($root))
                    ->searchable()
                    ->preload()
                    ->required($root->required_for_publish)
                    ->markAsRequired($root->required_for_publish)
                    ->helperText($root->required_for_publish ? '至少选择一项，支持多选。' : '可选，支持多选。');
            })
            ->all();
    }

    private static function categoryFieldName(int $rootId): string
    {
        return 'category_root_'.$rootId;
    }

    /**
     * @return array<int, string>
     */
    private static function categoryFieldNames(): array
    {
        return Category::query()
            ->roots()
            ->pluck('id')
            ->map(fn (mixed $id): string => self::categoryFieldName((int) $id))
            ->all();
    }

    /**
     * 提供给相册创建动作使用的分类字段名。
     *
     * @return array<int, string>
     */
    public static function categoryFieldNamesForForm(): array
    {
        return self::categoryFieldNames();
    }

    /**
     * @return array<int, int>
     */
    public static function categoryIdsFromFormData(array $data): array
    {
        return collect(self::categoryFieldNames())
            ->flatMap(fn (string $field): array => (array) ($data[$field] ?? []))
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, int>|int|null>
     */
    private static function categoryFormDataFromRecord(Album $record): array
    {
        $selectedByRoot = $record->categories()
            ->children()
            ->get(['categories.id', 'parent_id'])
            ->groupBy('parent_id');

        return Category::query()
            ->roots()
            ->get(['id', 'slug'])
            ->mapWithKeys(fn (Category $root): array => [
                self::categoryFieldName((int) $root->id) => in_array($root->slug, Category::MULTI_SELECT_ROOT_SLUGS, true)
                    ? $selectedByRoot->get($root->id, collect())->pluck('id')->all()
                    : $selectedByRoot->get($root->id, collect())->first()?->id,
            ])
            ->all();
    }
}
