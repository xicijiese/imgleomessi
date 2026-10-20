<x-filament-panels::page>
    <div class="flex flex-wrap gap-3" role="tablist" aria-label="分享推广管理">
        @foreach (['overview'=>'数据概览','links'=>'推广链接','visits'=>'访问归因','events'=>'操作记录'] as $key=>$label)
            <x-filament::button :color="$tab === $key ? 'primary' : 'gray'" wire:click="selectTab('{{ $key }}')" role="tab" :aria-selected="$tab === $key ? 'true' : 'false'">{{ $label }}</x-filament::button>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-4"><label>开始日期（北京时间）<input type="date" wire:model.live="dateFrom" class="block rounded-lg border-gray-300" /></label><label>结束日期<input type="date" wire:model.live="dateTo" class="block rounded-lg border-gray-300" /></label></div>
    @if ($tab === 'overview')
        @php($metrics = $this->metrics())
        <x-filament::section heading="推广统计">
            <p class="mb-4">{{ $metrics['worker'] ? '海报处理服务已连接' : '海报处理服务未连接，请检查队列守护进程' }}</p>
            <dl class="grid gap-5 sm:grid-cols-3"><div><dt>累计链接</dt><dd>{{ $metrics['links'] }}</dd></div><div><dt>累计分享操作</dt><dd>{{ $metrics['operations'] }}</dd></div><div><dt>所选日期有效访客</dt><dd>{{ $metrics['effective'] }}</dd></div></dl>
            <p class="mt-4">所选日期访问明细 {{ $metrics['visits'] }} 次，待验证 {{ $metrics['pending'] }} 次。访问明细保留 90 天；链接累计数和有效归因长期保留。旧分享日志不补算有效人数。</p>
        </x-filament::section>
        <x-filament::section heading="有效访客渠道分布">@forelse($metrics['channels'] as $item)<p>{{ $item['channel'] }}：{{ $item['total'] }} 位</p>@empty<p>暂无有效访客。</p>@endforelse</x-filament::section>
        <x-filament::section heading="有效访客日期趋势（最近 30 个有数据日期，UTC 汇总）">@forelse($metrics['days'] as $item)<p>{{ $item['day'] }}：{{ $item['total'] }} 位</p>@empty<p>暂无趋势数据。</p>@endforelse</x-filament::section>
    @else
        {{ $this->table }}
        @if($tab === 'visits')
            <x-filament::section heading="最近 30 条访问验证明细"><p>统计纠正不会自动撤销已获勋章，确认作弊时请在勋章运营中明确撤销。</p><div class="overflow-x-auto"><table class="w-full text-left"><thead><tr><th>链接</th><th>结果</th><th>原因</th><th>可见秒数</th><th>北京时间</th></tr></thead><tbody>@forelse($this->visits() as $item)<tr><td>{{ $item['code'] }}</td><td>{{ ['valid'=>'有效','pending'=>'待验证','duplicate'=>'重复','excluded'=>'排除'][$item['status']] ?? $item['status'] }}</td><td>{{ $item['reason'] }}</td><td>{{ $item['seconds'] }}</td><td>{{ $item['time'] }}</td></tr>@empty<tr><td colspan="5">暂无访问明细</td></tr>@endforelse</tbody></table></div></x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
