<?php

namespace App\Filament\Resources\SponsorshipPlans;

use App\Filament\Resources\SponsorshipPlans\Pages\ManageSponsorshipPlans;
use App\Models\SponsorshipPlan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class SponsorshipPlanResource extends Resource
{
    protected static ?string $model = SponsorshipPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = '赞助支持';

    protected static ?string $navigationLabel = '赞助方案';

    protected static ?string $modelLabel = '赞助方案';

    protected static ?string $pluralModelLabel = '赞助方案';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('方案名称')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('标识')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('amount_cents')
                    ->label('金额（分）')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                TextInput::make('duration_days')
                    ->label('支持者天数')
                    ->numeric()
                    ->helperText('留空表示一次性支持，不延长有效期。'),
                Select::make('badge_level')
                    ->label('徽章等级')
                    ->options(SponsorshipPlan::BADGE_LEVELS)
                    ->default('supporter')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('启用')
                    ->default(true),
                Textarea::make('benefits')
                    ->label('展示说明')
                    ->rows(5)
                    ->helperText('每行一条，将显示在前台方案卡片。')
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
                TextColumn::make('name')
                    ->label('方案名称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount_cents')
                    ->label('金额')
                    ->formatStateUsing(fn (int $state): string => '¥'.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('duration_days')
                    ->label('天数')
                    ->placeholder('一次性')
                    ->sortable(),
                TextColumn::make('badge_level')
                    ->label('徽章')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SponsorshipPlan::BADGE_LEVELS[$state] ?? $state),
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
                TernaryFilter::make('is_active')
                    ->label('启用状态')
                    ->trueLabel('已启用')
                    ->falseLabel('已停用')
                    ->native(false),
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
            'index' => ManageSponsorshipPlans::route('/'),
        ];
    }
}
