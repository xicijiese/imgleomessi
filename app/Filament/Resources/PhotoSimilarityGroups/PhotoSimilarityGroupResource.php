<?php

namespace App\Filament\Resources\PhotoSimilarityGroups;

use App\Filament\Resources\PhotoSimilarityGroups\Pages\ManagePhotoSimilarityGroups;
use App\Models\PhotoGovernanceRun;
use App\Models\PhotoSimilarityGroup;
use App\Services\PhotoSimilarityGovernance;
use App\Services\PublicMediaUrl;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PhotoSimilarityGroupResource extends Resource
{
    protected static ?string $model = PhotoSimilarityGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = '图库管理';

    protected static ?string $navigationLabel = '相似图管理';

    protected static ?string $modelLabel = '相似图管理';

    protected static ?int $navigationSort = 86;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['representative', 'photos'])->withCount('photos');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('组号')->sortable(),
            ImageColumn::make('preview')->label('代表图片')->getStateUsing(function ($record): ?string {
                $url = PublicMediaUrl::fromPublicDisk($record->representative?->thumbnail_key ?: $record->representative?->display_key);

                return $url ? url($url) : null;
            }),
            TextColumn::make('representative.title')->label('代表图片标题')->searchable(),
            TextColumn::make('photos_count')->label('图片数'),
            TextColumn::make('photos.title')->label('组内图片')->listWithLineBreaks()->limitList(4)->expandableLimitedList(),
            TextColumn::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i'),
        ])->defaultSort('id', 'desc')->recordActions([
            Action::make('compare')->label('查看组内图片')->modalHeading('组内图片比对')
                ->modalContent(fn ($record) => view('filament.photos.similarity-group', ['photos' => $record->photos]))
                ->modalSubmitAction(false)->modalCancelActionLabel('关闭'),
            Action::make('separate')->label('拆出图片')->color('warning')->requiresConfirmation()
                ->modalDescription('将所选图片移出本组，并记住它与本组其他图片的人工排除关系；重新分组后仍会保留此结论。')
                ->schema([Select::make('photo_id')->label('移出的图片')->options(fn ($record) => $record->photos->pluck('title', 'id')->all())->required()])
                ->action(function ($record, array $data): void {
                    abort_unless(auth()->user()?->isAdministrator(), 403);
                    app(PhotoSimilarityGovernance::class)->separate($record, (int) $data['photo_id'], auth()->user());
                }),
        ])->emptyStateHeading('暂无相似图分组')->emptyStateDescription(function (): string {
            $latest = PhotoGovernanceRun::query()->where('type', 'groups')->latest('id')->first();

            return $latest?->status === 'done'
                ? '最近一次分析已完成，未发现符合条件的相似图片。新增图片后可再次分析。'
                : '点击“分析并重新分组”，由后台处理服务分析现有图片并生成分组。';
        });
    }

    public static function getPages(): array
    {
        return ['index' => ManagePhotoSimilarityGroups::route('/')];
    }
}
