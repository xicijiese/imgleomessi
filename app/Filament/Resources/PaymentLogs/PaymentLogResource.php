<?php

namespace App\Filament\Resources\PaymentLogs;

use App\Filament\Resources\PaymentLogs\Pages\ManagePaymentLogs;
use App\Models\PaymentLog;
use App\Models\SponsorshipOrder;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PaymentLogResource extends Resource
{
    protected static ?string $model = PaymentLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = '赞助支持';

    protected static ?string $navigationLabel = '支付日志';

    protected static ?string $modelLabel = '支付日志';

    protected static ?string $pluralModelLabel = '支付日志';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sponsorship_order_id')
                    ->label('订单')
                    ->options(fn (): array => SponsorshipOrder::query()->latest()->limit(100)->pluck('order_no', 'id')->all())
                    ->disabled(),
                Select::make('channel')
                    ->label('渠道')
                    ->options(SponsorshipOrder::CHANNELS)
                    ->disabled(),
                Select::make('status')
                    ->label('状态')
                    ->options(PaymentLog::STATUSES)
                    ->disabled(),
                Textarea::make('event_type')
                    ->label('事件类型')
                    ->disabled(),
                Textarea::make('message')
                    ->label('消息')
                    ->rows(3)
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_no')
                    ->label('订单号')
                    ->placeholder('无订单')
                    ->searchable(),
                TextColumn::make('event_type')
                    ->label('事件')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channel')
                    ->label('渠道')
                    ->formatStateUsing(fn (string $state): string => SponsorshipOrder::CHANNELS[$state] ?? $state),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PaymentLog::STATUSES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('message')
                    ->label('消息')
                    ->limit(40),
                TextColumn::make('created_at')
                    ->label('记录时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(PaymentLog::STATUSES),
                SelectFilter::make('channel')
                    ->label('渠道')
                    ->options(SponsorshipOrder::CHANNELS),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentLogs::route('/'),
        ];
    }
}
