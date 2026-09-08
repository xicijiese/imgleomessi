<?php

namespace App\Filament\Resources\Comments;

use App\Filament\Resources\Comments\Pages\ManageComments;
use App\Models\Comment;
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

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = '社区与审核';

    protected static ?string $navigationLabel = '评论审核';

    protected static ?string $modelLabel = '评论';

    protected static ?string $pluralModelLabel = '评论';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('类型')
                    ->options(Comment::TYPES)
                    ->disabled(),
                Select::make('status')
                    ->label('状态')
                    ->options(Comment::STATUSES)
                    ->required(),
                Textarea::make('content')
                    ->label('内容')
                    ->rows(5)
                    ->disabled()
                    ->columnSpanFull(),
                Textarea::make('moderation_note')
                    ->label('内部审核备注')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Comment::TYPES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Comment::STATUSES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('risk_level')
                    ->label('风险')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'clean' || blank($state) ? '无' : (SensitiveWord::SEVERITIES[$state] ?? $state))
                    ->sortable(),
                TextColumn::make('content')
                    ->label('内容摘要')
                    ->limit(48)
                    ->searchable(),
                TextColumn::make('photo.title')
                    ->label('图片')
                    ->limit(28)
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('提交用户')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('reviewer.name')
                    ->label('审核人')
                    ->placeholder('未审核')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('提交时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(Comment::STATUSES),
                SelectFilter::make('type')
                    ->label('类型')
                    ->options(Comment::TYPES),
                SelectFilter::make('risk_level')
                    ->label('风险')
                    ->options(['clean' => '无'] + SensitiveWord::SEVERITIES),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->label('备注'),
                self::reviewAction('publish', '发布', 'published', 'success'),
                self::reviewAction('reject', '拒绝', 'rejected', 'danger'),
                self::reviewAction('hide', '隐藏', 'hidden', 'warning'),
                self::reviewAction('mark_deleted', '标记删除', 'deleted', 'danger'),
                self::reviewAction('reset_pending', '退回待审', 'pending', 'gray'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageComments::route('/'),
        ];
    }

    private static function reviewAction(string $name, string $label, string $status, string $color): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->form([
                Textarea::make('moderation_note')
                    ->label('内部处理备注')
                    ->rows(3),
            ])
            ->action(fn (Comment $record, array $data): bool => $record->review($status, auth()->user() instanceof User ? auth()->user() : null, $data['moderation_note'] ?? null));
    }
}
