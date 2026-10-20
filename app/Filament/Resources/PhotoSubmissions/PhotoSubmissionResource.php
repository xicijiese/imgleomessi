<?php

namespace App\Filament\Resources\PhotoSubmissions;

use App\Filament\Resources\PhotoSubmissions\Pages\ManagePhotoSubmissions;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoSubmission;
use App\Models\SubmissionEvent;
use App\Models\Tag;
use App\Services\PhotoSubmissionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class PhotoSubmissionResource extends Resource
{
    protected static ?string $model = PhotoSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = '社区与审核';

    protected static ?string $navigationLabel = '图片投稿审核';

    protected static ?string $modelLabel = '图片投稿';

    protected static ?string $pluralModelLabel = '图片投稿审核';

    protected static ?int $navigationSort = 45;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageGalleryContent() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && $record->status === 'pending';
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotIn('status', ['uploading', 'temporary'])->with(['user', 'reviewer', 'photo']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('revision')->required(),
            TextInput::make('metadata.title')->label('标题')->maxLength(255),
            TextInput::make('metadata.event_date')->label('图片日期')->type('date'),
            TextInput::make('metadata.source_url')->label('来源链接')->url(),
            Textarea::make('metadata.source_note')->label('来源说明')->required()->maxLength(2000),
            Textarea::make('metadata.description')->label('投稿说明')->maxLength(5000),
            Select::make('metadata.category_ids')->label('分类')->multiple()->searchable()->options(fn () => Category::query()->whereNotNull('parent_id')->where('visibility', 'public')->with('parent')->get()->mapWithKeys(fn ($item) => [$item->id => $item->parent->name.' / '.$item->name])->all()),
            Select::make('metadata.album_id')->label('相册')->searchable()->options(fn () => Album::query()->published()->pluck('title', 'id')->all()),
            Select::make('metadata.tag_ids')->label('标签')->multiple()->searchable()->options(fn () => Tag::query()->pluck('name', 'id')->all()),
            Toggle::make('metadata.show_credit')->label('投稿人自愿署名（仅展示本人选择）')->disabled()->dehydrated(),
            Textarea::make('internal_note')->label('内部备注（不对投稿人公开）'),
        ]);
    }

    private static function reviewAction(string $decision, string $label): Action
    {
        return Action::make($decision)->label($label)->color($decision === 'accepted' ? 'success' : 'danger')
            ->visible(fn (PhotoSubmission $record) => $record->status === 'pending' || ($decision === 'rejected' && $record->status === 'failed'))
            ->form([
                Hidden::make('revision')->default(fn (PhotoSubmission $record) => $record->revision),
                Textarea::make('note')->label('用户可见审核原因')->required()->maxLength(2000),
                Select::make('replacement_photo_id')->label('高清替换目标（可选）')->searchable()
                    ->helperText('只有确实为同一画面且分辨率改善时才选择；保留原档案及互动。留空建立新草稿。')
                    ->visible($decision === 'accepted')
                    ->getSearchResultsUsing(fn (string $search): array => Photo::query()->where('title', 'like', '%'.$search.'%')->limit(30)->get()->mapWithKeys(fn ($item) => [$item->id => '#'.$item->id.' '.$item->title.' '.$item->width.'×'.$item->height])->all()),
            ])->action(fn (PhotoSubmission $record, array $data) => app(PhotoSubmissionService::class)->review($record, auth()->user(), $decision, $data['note'], (int) $data['revision'], replacement: $data['replacement_photo_id'] ?? null));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('preview')->label('私有预览')->state(fn (PhotoSubmission $record) => $record->cleaned_at ? null : '/me/submissions/'.$record->id.'/image')->imageSize(70),
            TextColumn::make('original_filename')->label('原文件名')->searchable()->wrap(),
            TextColumn::make('user.name')->label('投稿人')->searchable(),
            TextColumn::make('batch_id')->label('批次')->limit(8)->searchable(),
            TextColumn::make('status')->label('状态')->badge()->formatStateUsing(fn ($state) => PhotoSubmission::STATUSES[$state]),
            TextColumn::make('duplicates')->label('精确重复')->state(fn ($record) => PhotoAnalysisResult::query()->where('sha256_hash', $record->sha256)->count().' 张'),
            TextColumn::make('similar_photo_ids')->label('相似档案 ID')->formatStateUsing(fn ($state) => is_array($state) ? implode('、', $state) : $state)->placeholder('无候选或分析中'),
            TextColumn::make('metadata.source_note')->label('来源说明')->limit(50),
            TextColumn::make('photo_id')->label('入库档案')->url(fn ($record) => $record->photo_id ? '/admin/photos?'.http_build_query(['filters' => ['id' => ['value' => $record->photo_id]]]) : null),
            TextColumn::make('error_message')->label('处理异常')->wrap(),
            TextColumn::make('review_note')->label('审核原因')->wrap(),
            TextColumn::make('reviewer.name')->label('审核人'),
            TextColumn::make('created_at')->label('投稿时间')->dateTime('Y-m-d H:i')->timezone('Asia/Shanghai')->sortable(),
            TextColumn::make('cleaned_at')->label('文件清理')->dateTime('Y-m-d H:i')->timezone('Asia/Shanghai')->placeholder('尚未清理'),
        ])->filters([
            SelectFilter::make('status')->label('状态')->options(PhotoSubmission::STATUSES),
            Filter::make('dates')->schema([TextInput::make('from')->label('起始日期')->type('date'), TextInput::make('until')->label('截止日期')->type('date')])
                ->query(fn (Builder $query, array $data) => $query->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
        ])->defaultSort('created_at', 'desc')->poll('10s')->recordActions([
            Action::make('preview')->label('查看图片及记录')->modalSubmitAction(false)->modalCancelActionLabel('关闭')
                ->modalContent(fn (PhotoSubmission $record) => view('filament.submission-preview', ['submission' => $record, 'events' => SubmissionEvent::query()->where('submission_id', $record->id)->oldest()->get()])),
            EditAction::make()->label('整理资料')->visible(fn ($record) => $record->status === 'pending')
                ->using(fn (PhotoSubmission $record, array $data) => app(PhotoSubmissionService::class)->amend($record, auth()->user(), $data)),
            static::reviewAction('accepted', '采纳'), static::reviewAction('rejected', '不采纳'),
            Action::make('retry')->label('重试入库')->visible(fn ($record) => $record->status === 'failed')->requiresConfirmation()
                ->action(fn ($record) => app(PhotoSubmissionService::class)->retry($record, auth()->user())),
            Action::make('revokeContribution')->label('纠正贡献')->color('danger')->visible(fn ($record) => auth()->user()->isAdministrator() && $record->status === 'accepted' && ! $record->contribution_revoked_at)
                ->form([Textarea::make('note')->label('纠正原因')->required()])->action(fn ($record, array $data) => app(PhotoSubmissionService::class)->revokeContribution($record, auth()->user(), $data['note'])),
        ])->toolbarActions([
            static::bulkReview('accepted', '批量采纳'), static::bulkReview('rejected', '批量不采纳'),
        ]);
    }

    private static function bulkReview(string $decision, string $label): BulkAction
    {
        return BulkAction::make($decision)->label($label)->form([Hidden::make('revisions')->default(fn (Collection $records): string => json_encode($records->mapWithKeys(fn ($record) => [$record->id => $record->revision])->all())), Textarea::make('note')->label('统一审核原因')->required()->maxLength(2000)])
            ->action(function (Collection $records, array $data) use ($decision): void {
                $processed = 0;
                $skipped = 0;
                $revisions = json_decode($data['revisions'] ?? '{}', true) ?: [];
                foreach ($records as $record) {
                    try {
                        app(PhotoSubmissionService::class)->review($record, auth()->user(), $decision, $data['note'], (int) ($revisions[$record->id] ?? 0));
                        $processed++;
                    } catch (ValidationException $error) {
                        $skipped++;
                    }
                }
                Notification::make()->title('处理 '.$processed.' 张，跳过 '.$skipped.' 张')->body('状态变化或重复图片会跳过，请逐张复核。')->send();
            })->deselectRecordsAfterCompletion();
    }

    public static function getPages(): array
    {
        return ['index' => ManagePhotoSubmissions::route('/')];
    }
}
