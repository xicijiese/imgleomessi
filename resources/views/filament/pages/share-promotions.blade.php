<x-filament-panels::page>
    <div class="admin-share-tabs" role="tablist" aria-label="分享推广管理">
        @foreach (['overview'=>'数据概览','links'=>'推广链接','visits'=>'访问归因','events'=>'操作记录'] as $key=>$label)
            <x-filament::button :color="$tab === $key ? 'primary' : 'gray'" wire:click="selectTab('{{ $key }}')" role="tab" :aria-selected="$tab === $key ? 'true' : 'false'">{{ $label }}</x-filament::button>
        @endforeach
    </div>
    <div class="admin-share-date-filters">
        <label>开始日期（北京时间）<input type="date" wire:model.live="dateFrom" /></label>
        <label>结束日期<input type="date" wire:model.live="dateTo" /></label>
    </div>
    @if ($tab === 'overview')
        @php($metrics = $this->metrics())
        <x-filament::section heading="推广统计">
            <p class="admin-share-service">{{ $metrics['worker'] ? '海报处理服务已连接' : '海报处理服务未连接，请检查队列守护进程' }}</p>
            <dl class="admin-share-metrics">
                <div><dt>累计链接</dt><dd>{{ $metrics['links'] }}</dd></div>
                <div><dt>累计分享操作</dt><dd>{{ $metrics['operations'] }}</dd></div>
                <div><dt>所选日期有效访客</dt><dd>{{ $metrics['effective'] }}</dd></div>
            </dl>
            <p class="admin-share-explanation">所选日期访问明细 {{ $metrics['visits'] }} 次，待验证 {{ $metrics['pending'] }} 次。访问明细保留 90 天；链接累计数和有效归因长期保留。旧分享日志不补算有效人数。</p>
        </x-filament::section>
        <div class="admin-share-panels">
            <x-filament::section heading="有效访客渠道分布">
                @forelse($metrics['channels'] as $item)
                    <p class="admin-share-distribution"><span>{{ $item['channel'] }}</span><span>{{ $item['total'] }} 位</span></p>
                @empty
                    <p>暂无有效访客。</p>
                @endforelse
            </x-filament::section>
            <x-filament::section heading="有效访客日期趋势（最近 30 个有数据日期，UTC 汇总）">
                @forelse($metrics['days'] as $item)
                    <p class="admin-share-distribution"><span>{{ $item['day'] }}</span><span>{{ $item['total'] }} 位</span></p>
                @empty
                    <p>暂无趋势数据。</p>
                @endforelse
            </x-filament::section>
        </div>
    @else
        {{ $this->table }}
        @if($tab === 'visits')
            <x-filament::section heading="最近 30 条访问验证明细">
                <p class="admin-share-explanation">统计纠正不会自动撤销已获勋章，确认作弊时请在勋章运营中明确撤销。</p>
                <div class="admin-detail-table-scroll">
                    <table class="admin-detail-table">
                        <thead><tr><th>链接</th><th>结果</th><th>原因</th><th>可见秒数</th><th>北京时间</th></tr></thead>
                        <tbody>
                            @forelse($this->visits() as $item)
                                <tr><td>{{ $item['code'] }}</td><td>{{ ['valid'=>'有效','pending'=>'待验证','duplicate'=>'重复','excluded'=>'排除'][$item['status']] ?? $item['status'] }}</td><td>{{ $item['reason'] }}</td><td>{{ $item['seconds'] }}</td><td>{{ $item['time'] }}</td></tr>
                            @empty
                                <tr><td colspan="5">暂无访问明细</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
