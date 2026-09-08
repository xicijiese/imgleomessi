<?php

namespace App\Filament\Resources\PhotoSimilarityCandidates;

use App\Filament\Resources\PhotoSimilarityCandidates\Pages\ManagePhotoSimilarityCandidates;
use App\Models\Photo;
use App\Models\PhotoSimilarityCandidate;
use App\Models\User;
use App\Services\PhotoSimilarityService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PhotoSimilarityCandidateResource extends Resource
{
    protected static ?string $model = PhotoSimilarityCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = '图库管理';

    protected static ?string $navigationLabel = '相似候选';

    protected static ?string $modelLabel = '相似候选';

    protected static ?string $pluralModelLabel = '相似候选';

    protected static ?int $navigationSort = 85;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'photo.analysisResult',
            'candidatePhoto.analysisResult',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('photo.title')
                    ->label('图片 A')
                    ->limit(28)
                    ->searchable(),
                TextColumn::make('candidatePhoto.title')
                    ->label('图片 B')
                    ->limit(28)
                    ->searchable(),
                TextColumn::make('photo.status')
                    ->label('图片 A 状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Photo::STATUSES[$state] ?? $state),
                TextColumn::make('candidatePhoto.status')
                    ->label('图片 B 状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Photo::STATUSES[$state] ?? $state),                TextColumn::make('similarity_score')
                    ->label('相似度')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('distance')
                    ->label('感知距离')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('人工结论')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PhotoSimilarityCandidate::STATUSES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('photo.analysisResult.sha256_hash')
                    ->label('A 的 SHA-256')
                    ->limit(12)
                    ->placeholder('未分析'),
                TextColumn::make('candidatePhoto.analysisResult.sha256_hash')
                    ->label('B 的 SHA-256')
                    ->limit(12)
                    ->placeholder('未分析'),
                TextColumn::make('reviewer.name')
                    ->label('处理人')
                    ->placeholder('未处理'),
                TextColumn::make('reviewed_at')
                    ->label('处理时间')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('未处理')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('人工结论')
                    ->options(PhotoSimilarityCandidate::STATUSES),
                Filter::make('photo')
                    ->label('指定图片')
                    ->schema([
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
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['photo_id'] ?? null,
                        fn (Builder $query, int|string $photoId): Builder => $query->where(function (Builder $query) use ($photoId): void {
                            $query->where('photo_id', $photoId)->orWhere('candidate_photo_id', $photoId);
                        }),
                    )),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('keepSeparate')
                    ->label('保留独立')
                    ->color('success')
                    ->visible(fn (PhotoSimilarityCandidate $record): bool => $record->status !== 'kept_separate')
                    ->requiresConfirmation()
                    ->action(fn (PhotoSimilarityCandidate $record): PhotoSimilarityCandidate => app(PhotoSimilarityService::class)->review(
                        $record,
                        'kept_separate',
                        auth()->user() instanceof User ? auth()->user() : null,
                    )),
                Action::make('confirmDuplicate')
                    ->label('确认重复候选')
                    ->color('warning')
                    ->visible(fn (PhotoSimilarityCandidate $record): bool => $record->status !== 'confirmed_duplicate')
                    ->requiresConfirmation()
                    ->action(fn (PhotoSimilarityCandidate $record): PhotoSimilarityCandidate => app(PhotoSimilarityService::class)->review(
                        $record,
                        'confirmed_duplicate',
                        auth()->user() instanceof User ? auth()->user() : null,
                    )),
                Action::make('ignore')
                    ->label('忽略')
                    ->color('gray')
                    ->visible(fn (PhotoSimilarityCandidate $record): bool => $record->status !== 'ignored')
                    ->action(fn (PhotoSimilarityCandidate $record): PhotoSimilarityCandidate => app(PhotoSimilarityService::class)->review(
                        $record,
                        'ignored',
                        auth()->user() instanceof User ? auth()->user() : null,
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePhotoSimilarityCandidates::route('/'),
        ];
    }
}