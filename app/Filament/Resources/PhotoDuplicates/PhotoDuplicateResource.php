<?php

namespace App\Filament\Resources\PhotoDuplicates;

use App\Filament\Resources\PhotoDuplicates\Pages\ManagePhotoDuplicates;
use App\Models\Photo;
use App\Models\PhotoDeduplicationLog;
use App\Models\PhotoSimilarityCandidate;
use App\Services\PhotoSimilarityService;
use App\Services\PhotoStorage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class PhotoDuplicateResource extends Resource
{
    protected static ?string $model = PhotoSimilarityCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = '图库管理';

    protected static ?string $navigationLabel = '图片查重';

    protected static ?string $modelLabel = '图片查重';

    protected static ?int $navigationSort = 87;

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
        return parent::getEloquentQuery()->with(['photo.analysisResult', 'candidatePhoto.analysisResult', 'photo.albums', 'candidatePhoto.albums'])
            ->where('distance', '<=', PhotoSimilarityService::DISTANCE_THRESHOLD)
            ->whereHas('photo', fn ($photos) => $photos->where('status', '!=', 'archived'))
            ->whereHas('candidatePhoto', fn ($photos) => $photos->where('status', '!=', 'archived'));
    }

    public static function table(Table $table): Table
    {
        $columns = [];
        foreach (['photo' => '图片 A', 'candidatePhoto' => '图片 B'] as $relation => $label) {
            $columns[] = ImageColumn::make($relation.'.thumbnail_key')->label($label)->imageSize(88)
                ->state(fn ($record) => app(PhotoStorage::class)->url($record->{$relation}?->thumbnail_key ?: $record->{$relation}?->display_key))
                ->checkFileExistence(false);
            $columns[] = TextColumn::make($relation.'.title')->label($label.'标题')->wrap()->searchable()
                ->extraCellAttributes(['class' => 'admin-object-cell']);
            $columns[] = TextColumn::make($relation.'.albums.title')
                ->label($label.'所属相册')
                ->listWithLineBreaks()
                ->wrap()
                ->placeholder('无关联相册');
        }
        $columns[] = TextColumn::make('similarity_score')->label('画面相似度')->suffix('%')->alignEnd()->sortable();
        $columns[] = TextColumn::make('result')->label('检查结果')
            ->state(fn ($record) => $record->isExactFile() ? '文件完全相同' : '画面相似，需人工确认');
        $actions = [Action::make('compare')->label('查看对照')->modalWidth('6xl')->stickyModalHeader()->stickyModalFooter()
            ->modalContent(fn ($record) => view('filament.photos.similarity-group', ['photos' => collect([$record->photo, $record->candidatePhoto])]))
            ->modalSubmitAction(false)->modalCancelActionLabel('关闭')];
        foreach (['keepA' => ['photo_id', 'candidate_photo_id', '保留 A，删除 B'], 'keepB' => ['candidate_photo_id', 'photo_id', '保留 B，删除 A']] as $name => [$keep, $remove, $label]) {
            $actions[] = Action::make($name)->label($label)->color('danger')->requiresConfirmation()
                ->modalHeading($label)->modalDescription('请先查看对照。删除会移除图片档案及其相册关联、评论、点赞和收藏，不合并到保留图片，也不直接清理存储文件。有关联投稿或受保护历史记录的图片不能删除。此操作不可撤销。')
                ->modalWidth('3xl')->stickyModalHeader()->stickyModalFooter()
                ->action(function ($record) use ($keep, $remove): void {
                    abort_unless(auth()->user()?->isAdministrator(), 403);
                    try {
                        DB::transaction(function () use ($record, $keep, $remove): void {
                            $pair = PhotoSimilarityCandidate::query()->lockForUpdate()->findOrFail($record->id);
                            $photos = Photo::query()->whereIn('id', [$pair->{$keep}, $pair->{$remove}])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                            $retained = $photos->get($pair->{$keep});
                            $deleted = $photos->get($pair->{$remove});
                            abort_unless($retained && $deleted, 409, '图片记录已变化，请刷新后重试。');
                            if ($retained->status === 'archived') {
                                throw ValidationException::withMessages(['photo' => '保留图片已归档，请刷新检查结果。']);
                            }
                            if (PhotoDeduplicationLog::where('photo_id', $deleted->id)->orWhere('retained_photo_id', $deleted->id)->exists()) {
                                throw ValidationException::withMessages(['photo' => '该图片有关联的历史处理记录，不能直接删除。']);
                            }
                            $deleted->delete();
                        });
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();

                        return;
                    }
                    Notification::make()->success()->title('已删除选中的图片档案')->send();
                });
        }
        $filter = SelectFilter::make('result')->label('检查结果')->options(['exact' => '文件完全相同', 'similar' => '画面相似，需人工确认'])
            ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                'exact' => $query->whereNot(fn (Builder $nested) => $nested->notExactFile()),
                'similar' => $query->notExactFile(),
                default => $query,
            });

        return $table->extraAttributes(['class' => 'admin-content-table'])->columns($columns)->recordActions($actions)->filters([$filter])
            ->defaultSort('distance')->paginated([20, 50, 100])->defaultPaginationPageOption(20)
            ->emptyStateHeading('暂无疑似重复图片')
            ->emptyStateDescription('点击“开始检查”，在后台检查现有图片；检查不会自动删除图片。');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePhotoDuplicates::route('/')];
    }
}
