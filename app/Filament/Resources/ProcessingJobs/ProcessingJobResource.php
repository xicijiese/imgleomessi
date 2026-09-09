<?php

namespace App\Filament\Resources\ProcessingJobs;

use App\Filament\Resources\ProcessingJobs\Pages\ManageProcessingJobs;
use App\Models\Photo;
use App\Models\ProcessingJob;
use App\Services\PhotoProcessingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ProcessingJobResource extends Resource
{
    protected static ?string $model = ProcessingJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = '系统与运维';

    protected static ?string $navigationLabel = '图片入库处理';

    protected static ?string $modelLabel = '入库处理任务';

    protected static ?string $pluralModelLabel = '入库处理任务';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', '!=', 'labels');
    }

    private static function activeTypeOptions(): array
    {
        return array_diff_key(ProcessingJob::TYPES, ['labels' => true]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('photo_id')
                    ->label('图片')
                    ->options(fn (): array => Photo::query()
                        ->latest('updated_at')
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(fn (Photo $photo): array => [
                            $photo->id => $photo->title ?: '图片 #'.$photo->id,
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),
                Select::make('type')
                    ->label('任务类型')
                    ->options(self::activeTypeOptions())
                    ->required(),
                Select::make('status')
                    ->label('状态')
                    ->options(ProcessingJob::STATUSES)
                    ->default('pending')
                    ->required(),
                TextInput::make('attempts')
                    ->label('尝试次数')
                    ->numeric()
                    ->default(0),
                Textarea::make('payload')
                    ->label('任务参数 JSON')
                    ->rows(4)
                    ->placeholder('P1-11 默认无需填写')
                    ->columnSpanFull(),
                Textarea::make('error_message')
                    ->label('错误信息')
                    ->rows(3)
                    ->disabled()
                    ->columnSpanFull(),
                DateTimePicker::make('processed_at')
                    ->label('处理完成时间')
                    ->seconds(false)
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('任务类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProcessingJob::TYPES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProcessingJob::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'done' => 'success',
                        'failed' => 'danger',
                        'running' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('photo.title')
                    ->label('图片')
                    ->limit(32)
                    ->searchable(),
                TextColumn::make('attempts')
                    ->label('尝试')
                    ->sortable(),
                TextColumn::make('error_message')
                    ->label('错误')
                    ->limit(36)
                    ->placeholder('无'),
                TextColumn::make('processed_at')
                    ->label('完成时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('未完成'),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('任务类型')
                    ->options(self::activeTypeOptions()),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(ProcessingJob::STATUSES),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('process')
                    ->label('手动补处理')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (ProcessingJob $record): void {
                        $job = app(PhotoProcessingService::class)->process($record);

                        Notification::make()
                            ->title($job->status === 'done' ? '补处理完成' : '补处理失败')
                            ->body($job->error_message)
                            ->{$job->status === 'done' ? 'success' : 'danger'}()
                            ->send();
                    }),
                Action::make('retry')
                    ->label('重试失败任务')
                    ->color('warning')
                    ->visible(fn (ProcessingJob $record): bool => $record->status === 'failed')
                    ->requiresConfirmation()
                    ->action(function (ProcessingJob $record, PhotoProcessingService $service): void {
                        $service->queueRetry($record);

                        Notification::make()
                            ->title('失败任务已重新入队')
                            ->body('后台 Worker 将继续处理该任务。')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('retryFailed')
                        ->label('批量重试失败任务')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, PhotoProcessingService $service): void {
                            $count = 0;

                            foreach ($records->where('status', 'failed') as $record) {
                                $service->queueRetry($record);
                                $count++;
                            }

                            Notification::make()
                                ->title('失败任务已重新入队')
                                ->body("已重新入队 {$count} 个失败任务。")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProcessingJobs::route('/'),
        ];
    }
}
