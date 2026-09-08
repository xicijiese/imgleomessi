<?php

namespace App\Filament\Resources\SponsorshipOrders;

use App\Filament\Resources\SponsorshipOrders\Pages\ManageSponsorshipOrders;
use App\Models\SponsorshipOrder;
use App\Models\SponsorshipPlan;
use App\Models\User;
use App\Services\SponsorshipService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SponsorshipOrderResource extends Resource
{
    protected static ?string $model = SponsorshipOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = '赞助支持';

    protected static ?string $navigationLabel = '赞助订单';

    protected static ?string $modelLabel = '赞助订单';

    protected static ?string $pluralModelLabel = '赞助订单';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_no')
                    ->label('订单号')
                    ->disabled(),
                Select::make('user_id')
                    ->label('用户')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->disabled(),
                Select::make('sponsorship_plan_id')
                    ->label('赞助方案')
                    ->options(fn (): array => SponsorshipPlan::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->disabled(),
                TextInput::make('amount_cents')
                    ->label('金额（分）')
                    ->numeric()
                    ->disabled(),
                Select::make('channel')
                    ->label('渠道')
                    ->options(SponsorshipOrder::CHANNELS)
                    ->required(),
                Select::make('status')
                    ->label('状态')
                    ->options(SponsorshipOrder::STATUSES)
                    ->required(),
                TextInput::make('transaction_id')
                    ->label('交易号')
                    ->maxLength(255),
                Textarea::make('admin_note')
                    ->label('内部备注')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_no')
                    ->label('订单号')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('user.name')
                    ->label('用户')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan.name')
                    ->label('方案')
                    ->placeholder('已删除')
                    ->sortable(),
                TextColumn::make('amount_cents')
                    ->label('金额')
                    ->formatStateUsing(fn (int $state): string => '¥'.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SponsorshipOrder::STATUSES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('channel')
                    ->label('渠道')
                    ->formatStateUsing(fn (string $state): string => SponsorshipOrder::CHANNELS[$state] ?? $state),
                TextColumn::make('paid_at')
                    ->label('支付时间')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('未支付')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(SponsorshipOrder::STATUSES),
                SelectFilter::make('channel')
                    ->label('渠道')
                    ->options(SponsorshipOrder::CHANNELS),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->label('备注/状态'),
                Action::make('markPaid')
                    ->label('标记已支付')
                    ->color('success')
                    ->visible(fn (SponsorshipOrder $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('admin_note')
                            ->label('内部备注')
                            ->rows(3),
                    ])
                    ->action(fn (SponsorshipOrder $record, array $data): SponsorshipOrder => app(SponsorshipService::class)->markPaid($record, 'manual', auth()->user() instanceof User ? auth()->user() : null, $data['admin_note'] ?? null)),
                Action::make('close')
                    ->label('关闭')
                    ->color('warning')
                    ->visible(fn (SponsorshipOrder $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('admin_note')
                            ->label('内部备注')
                            ->rows(3),
                    ])
                    ->action(fn (SponsorshipOrder $record, array $data): SponsorshipOrder => app(SponsorshipService::class)->close($record, auth()->user() instanceof User ? auth()->user() : null, $data['admin_note'] ?? null)),
                Action::make('refund')
                    ->label('标记退款')
                    ->color('danger')
                    ->visible(fn (SponsorshipOrder $record): bool => $record->status === 'paid')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('admin_note')
                            ->label('内部备注')
                            ->rows(3),
                    ])
                    ->action(fn (SponsorshipOrder $record, array $data): SponsorshipOrder => app(SponsorshipService::class)->refund($record, auth()->user() instanceof User ? auth()->user() : null, $data['admin_note'] ?? null)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSponsorshipOrders::route('/'),
        ];
    }
}
