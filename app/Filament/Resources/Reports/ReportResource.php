<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\ManageReports;
use App\Models\Report;
use App\Models\SensitiveWord;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = '社区与审核';

    protected static ?string $navigationLabel = '举报 / 风险内容处理';

    protected static ?string $modelLabel = '举报';

    protected static ?string $pluralModelLabel = '举报';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('target_type')
                    ->label('目标类型')
                    ->options(Report::TARGET_TYPES)
                    ->disabled(),
                Select::make('reason')
                    ->label('举报原因')
                    ->options(Report::REASONS)
                    ->disabled(),
                Select::make('status')
                    ->label('状态')
                    ->options(Report::STATUSES)
                    ->required(),
                Textarea::make('details')
                    ->label('补充说明')
                    ->rows(4)
                    ->disabled()
                    ->columnSpanFull(),
                Textarea::make('internal_note')
                    ->label('内部处理备注')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Report::STATUSES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('reason')
                    ->label('原因')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Report::REASONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('risk_level')
                    ->label('风险')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'clean' || blank($state) ? '无' : (SensitiveWord::SEVERITIES[$state] ?? $state))
                    ->sortable(),
                TextColumn::make('comment.content')
                    ->label('目标评论')
                    ->limit(42),
                TextColumn::make('reporter.name')
                    ->label('举报人')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('handler.name')
                    ->label('处理人')
                    ->placeholder('未处理')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('举报时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(Report::STATUSES),
                SelectFilter::make('target_type')
                    ->label('目标类型')
                    ->options(Report::TARGET_TYPES),
                SelectFilter::make('reason')
                    ->label('举报原因')
                    ->options(Report::REASONS),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->label('备注'),
                Action::make('resolve')
                    ->label('处理')
                    ->color('success')
                    ->form([
                        Textarea::make('internal_note')
                            ->label('内部处理备注')
                            ->rows(3),
                    ])
                    ->action(fn (Report $record, array $data): bool => $record->resolve(auth()->user() instanceof User ? auth()->user() : null, $data['internal_note'] ?? null)),
                Action::make('resolve_and_hide')
                    ->label('处理并隐藏评论')
                    ->color('warning')
                    ->form([
                        Textarea::make('internal_note')
                            ->label('内部处理备注')
                            ->rows(3),
                    ])
                    ->action(fn (Report $record, array $data): bool => $record->resolve(auth()->user() instanceof User ? auth()->user() : null, $data['internal_note'] ?? null, true)),
                Action::make('reject')
                    ->label('驳回')
                    ->color('danger')
                    ->form([
                        Textarea::make('internal_note')
                            ->label('内部处理备注')
                            ->rows(3),
                    ])
                    ->action(fn (Report $record, array $data): bool => $record->reject(auth()->user() instanceof User ? auth()->user() : null, $data['internal_note'] ?? null)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReports::route('/'),
        ];
    }
}
