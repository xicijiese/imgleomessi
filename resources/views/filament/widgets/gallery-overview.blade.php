<x-filament-widgets::widget>
    <div class="admin-dashboard">
        <div class="admin-dashboard-heading">
            <h2>图库概览</h2>
            <p>更新于 {{ $snapshot['updated_at'] }}（北京时间）· 一分钟缓存，刷新页面更新</p>
        </div>

        <div @class(['admin-dashboard-metrics', 'admin-dashboard-metrics--three' => count($overview) === 3])>
            @foreach ($overview as $item)
                <a href="{{ $item['url'] }}" class="admin-dashboard-metric">
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ number_format($item['count']) }}</strong>
                    <span class="admin-dashboard-link">查看列表 →</span>
                </a>
            @endforeach
        </div>

        <div class="admin-dashboard-work">
            <x-filament::section heading="待办事项">
                <div class="admin-dashboard-rows">
                    @foreach ($todos as $item)
                        <a href="{{ $item['url'] }}" class="admin-dashboard-row">
                            <span>{{ $item['label'] }}</span>
                            <strong>{{ number_format($item['count']) }}</strong>
                            <span class="admin-dashboard-link">去处理 →</span>
                        </a>
                    @endforeach
                </div>
                <p class="admin-dashboard-note">普通评论直接发布，此处仅列出需要人工处理的事项。</p>
            </x-filament::section>

            @if ($admin)
                <x-filament::section heading="入库处理">
                    <div class="admin-dashboard-rows">
                        @foreach ($processing as $item)
                            <a href="{{ $item['url'] }}" @class(['admin-dashboard-row', 'admin-dashboard-failure' => $item['failed'] && $item['count'] > 0])>
                                <span>{{ $item['label'] }}</span>
                                <strong>{{ number_format($item['count']) }}</strong>
                                <span class="admin-dashboard-link">查看任务 →</span>
                            </a>
                        @endforeach
                    </div>
                    <p class="admin-dashboard-note">按任务条数统计，同一张图片可能对应多个任务。</p>
                </x-filament::section>
            @endif
        </div>

        @if ($admin)
            <div class="admin-dashboard-work">
                <x-filament::section heading="最近上传任务">
                    <div class="admin-dashboard-rows">
                        @forelse ($snapshot['batches'] as $batch)
                            <a class="admin-dashboard-activity" href="{{ \App\Filament\Resources\PhotoUploadBatches\PhotoUploadBatchResource::getUrl('photos', ['record' => $batch['id']]) }}">
                                <div><strong>任务 #{{ $batch['id'] }} · {{ $batch['title'] }}</strong><span>{{ $batch['status'] }} · 成功 {{ $batch['success'] }} / {{ $batch['total'] }} 张</span></div>
                                <time>{{ $batch['time'] }}</time>
                            </a>
                        @empty
                            <p class="admin-dashboard-note">暂无上传任务。</p>
                        @endforelse
                    </div>
                    <a class="admin-dashboard-all" href="{{ \App\Filament\Resources\PhotoUploadBatches\PhotoUploadBatchResource::getUrl() }}">查看全部上传任务 →</a>
                </x-filament::section>

                <x-filament::section heading="最近管理员操作">
                    <div class="admin-dashboard-rows">
                        @forelse ($snapshot['audits'] as $audit)
                            <div class="admin-dashboard-activity">
                                <div><strong>{{ $audit['actor'] }}</strong><span>{{ $audit['action'] }}</span></div>
                                <time>{{ $audit['time'] }}</time>
                            </div>
                        @empty
                            <p class="admin-dashboard-note">暂无管理员操作记录。</p>
                        @endforelse
                    </div>
                    <a class="admin-dashboard-all" href="{{ \App\Filament\Resources\AdminAuditLogs\AdminAuditLogResource::getUrl() }}">查看全部操作记录 →</a>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
