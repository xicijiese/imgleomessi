<?php

namespace App\Filament\Pages;

use App\Models\ShareEvent;
use App\Models\ShareLink;
use App\Models\ShareReferral;
use App\Models\ShareVisit;
use App\Services\QueueWorkerStatus;
use App\Services\SharePromotion;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

class SharePromotions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static string|UnitEnum|null $navigationGroup = '社区与审核';

    protected static ?string $navigationLabel = '分享推广';

    protected static ?string $title = '分享推广';

    protected static ?int $navigationSort = 47;

    protected string $view = 'filament.pages.share-promotions';

    #[Url]
    public string $tab = 'overview';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public function selectTab(string $tab): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(in_array($tab, ['overview', 'links', 'visits', 'events']), 422);
        $this->tab = $tab;
        $this->tableSearch = '';
        $this->tableFilters = null;
        $this->tableSort = null;
        $this->resetTable();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function metrics(): array
    {
        abort_unless(static::canAccess(), 403);
        $visits = $this->dates(ShareVisit::query());

        return ['worker' => app(QueueWorkerStatus::class)->available(), 'links' => ShareLink::count(), 'operations' => ShareLink::sum('operations'), 'visits' => (clone $visits)->count(), 'pending' => (clone $visits)->where('status', 'pending')->count(), 'effective' => $this->dates(ShareReferral::where('status', 'valid'))->count(), 'channels' => $this->dates(ShareReferral::where('status', 'valid'))->join('share_links', 'share_links.id', '=', 'share_referrals.share_link_id')->select('channel', DB::raw('count(*) as total'))->groupBy('channel')->get()->toArray(), 'days' => $this->dates(ShareReferral::where('status', 'valid'))->selectRaw('DATE(created_at) as day, count(*) as total')->groupByRaw('DATE(created_at)')->orderByDesc('day')->limit(30)->get()->toArray()];
    }

    private function dates($query)
    {
        $column = $query->getModel()->getTable().'.created_at';
        if ($this->dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateFrom)) {
            $query->where($column, '>=', Carbon::parse($this->dateFrom, 'Asia/Shanghai')->utc());
        }
        if ($this->dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateTo)) {
            $query->where($column, '<', Carbon::parse($this->dateTo, 'Asia/Shanghai')->addDay()->utc());
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        abort_unless(static::canAccess(), 403);
        $query = match ($this->tab) {
            'visits' => ShareReferral::with('link.user'), 'events' => ShareEvent::with('actor'), default => ShareLink::with('user')->withCount(['referrals as effective_count' => fn ($q) => $q->where('status', 'valid')])
        };
        $columns = match ($this->tab) {
            'visits' => [TextColumn::make('id')->label('归因 ID'), TextColumn::make('link.code')->label('分享链接')->searchable(), TextColumn::make('link.user.name')->label('分享者')->searchable(), TextColumn::make('status')->label('结果')->formatStateUsing(fn ($state) => ['valid' => '有效', 'revoked' => '已纠正排除', 'merged' => '身份合并去重'][$state] ?? $state), TextColumn::make('created_at')->label('归因时间')->dateTime()->timezone('Asia/Shanghai')],
            'events' => [TextColumn::make('actor.name')->label('管理员')->placeholder('系统'), TextColumn::make('event')->label('操作')->formatStateUsing(fn ($state) => ['restored' => '恢复', 'revoked' => '停用或纠正', 'merged' => '身份合并', 'self_excluded' => '本人排除'][$state] ?? $state), TextColumn::make('share_link_id')->label('链接 ID'), TextColumn::make('share_referral_id')->label('归因 ID'), TextColumn::make('reason')->label('原因')->wrap(), TextColumn::make('created_at')->label('时间')->dateTime()->timezone('Asia/Shanghai')],
            default => [TextColumn::make('code')->label('短链接标识')->searchable()->copyable(), TextColumn::make('user.name')->label('分享者')->placeholder('游客')->searchable(), TextColumn::make('target_type')->label('内容类型')->formatStateUsing(fn ($state) => ['home' => '网站首页', 'photo' => '图片', 'album' => '相册', 'topic' => '专题'][$state] ?? $state), TextColumn::make('target_key')->label('目标')->searchable()->limit(25), TextColumn::make('channel')->label('渠道')->formatStateUsing(fn ($state) => ['copy_link' => '复制链接', 'native_share' => '系统分享', 'weibo' => '微博', 'wechat_qr' => '微信二维码', 'poster' => '分享海报'][$state] ?? $state), IconColumn::make('is_active')->label('启用')->boolean(), TextColumn::make('operations')->label('操作'), TextColumn::make('visits')->label('访问'), TextColumn::make('unique_visitors')->label('独立浏览器'), TextColumn::make('effective_count')->label('有效访客')],
        };
        $actions = $this->tab === 'links' ? [Action::make('toggle')->label(fn (ShareLink $record) => $record->is_active ? '停用链接' : '恢复链接')->schema([Textarea::make('reason')->label('原因')->required()->maxLength(1000)])->action(fn (ShareLink $record, array $data) => app(SharePromotion::class)->moderate(auth()->user(), 'link', $record->id, ! $record->is_active, $data['reason']))] : [];
        if ($this->tab === 'visits') {
            $actions[] = Action::make('referrals')->label(fn (ShareReferral $record) => $record->status === 'valid' ? '纠正为无效' : '恢复有效')->visible(fn (ShareReferral $record) => $record->status !== 'merged')->schema([Textarea::make('reason')->label('纠正原因')->required()->maxLength(1000)])->action(fn (ShareReferral $record, array $data) => app(SharePromotion::class)->moderate(auth()->user(), 'referral', $record->id, $record->status !== 'valid', $data['reason']));
        }

        return $table->modelLabel(match ($this->tab) {
            'visits' => '访问归因','events' => '操作记录', default => '分享链接'
        })->emptyStateHeading(match ($this->tab) {
            'visits' => '暂无有效归因记录','events' => '暂无操作记录', default => '暂无分享链接'
        })->query($this->dates($query))->columns($columns)->filters([SelectFilter::make('status')->label('归因状态')->options(['valid' => '有效', 'revoked' => '已纠正排除', 'merged' => '身份合并'])->visible($this->tab === 'visits')])->recordActions($actions)->defaultSort('created_at', 'desc')->paginated([15, 30, 50]);
    }

    public function visits(): array
    {
        abort_unless(static::canAccess(), 403);

        return $this->dates(ShareVisit::with('link'))->latest()->limit(30)->get()->map(fn ($visit) => ['code' => $visit->link->code, 'status' => $visit->status, 'reason' => $visit->reason, 'seconds' => $visit->visible_seconds, 'time' => $visit->created_at->timezone('Asia/Shanghai')->format('Y-m-d H:i')])->all();
    }
}
