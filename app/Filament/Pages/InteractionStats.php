<?php

namespace App\Filament\Pages;

use App\Services\PhotoInteractionRankings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class InteractionStats extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = '社区与审核';

    protected static ?string $navigationLabel = '互动统计';

    protected static ?string $title = '互动统计';

    protected static ?string $slug = 'interaction-stats';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.interaction-stats';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters): array => $this->records($filters))
            ->columns([
                TextColumn::make('rank')
                    ->label('排名')
                    ->badge(),
                TextColumn::make('title')
                    ->label('图片')
                    ->description(fn (array $record): string => $record['category_summary'])
                    ->searchable()
                    ->wrap(),
                TextColumn::make('metrics.hot_score')
                    ->label('热度')
                    ->sortable(),
                TextColumn::make('metrics.likes')
                    ->label('点赞')
                    ->sortable(),
                TextColumn::make('metrics.favorites')
                    ->label('收藏')
                    ->sortable(),
                TextColumn::make('metrics.comments')
                    ->label('评论')
                    ->sortable(),
                TextColumn::make('metrics.shares')
                    ->label('分享')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('榜单类型')
                    ->options(fn (): array => app(PhotoInteractionRankings::class)->typeOptions())
                    ->default('hot'),
                SelectFilter::make('window')
                    ->label('时间范围')
                    ->options(fn (): array => app(PhotoInteractionRankings::class)->windowOptions())
                    ->default('all'),
            ])
            ->recordActions([
                Action::make('view_public')
                    ->label('前台详情')
                    ->url(fn (array $record): string => $record['url'])
                    ->openUrlInNewTab(),
                Action::make('manage_photo')
                    ->label('图片管理')
                    ->url(fn (array $record): string => $record['admin_url']),
            ])
            ->emptyStateHeading('暂无互动数据')
            ->emptyStateDescription('产生点赞、收藏、已发布普通评论或分享记录后，这里会自动展示图片互动排行。')
            ->paginated([10, 25, 50]);
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array<int, array<string, mixed>>
     */
    private function records(?array $filters): array
    {
        $type = $this->filterValue($filters, 'type', 'hot');
        $window = $this->filterValue($filters, 'window', 'all');

        return collect(app(PhotoInteractionRankings::class)->adminRows($type, $window, 50))
            ->map(function (array $row): array {
                $row['key'] = (string) $row['id'];

                return $row;
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    private function filterValue(?array $filters, string $key, string $default): string
    {
        $value = $filters[$key]['value'] ?? $filters[$key] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
