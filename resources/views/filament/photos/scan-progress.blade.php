@php
    $active = in_array($run->status, ['pending', 'running'], true);
    $percent = $run->progressPercent();
    $label = '图片查重';
    $state = ['pending' => '排队中', 'running' => '正在处理', 'done' => '已完成', 'failed' => '处理失败'][$run->status] ?? $run->status;
    $hasImageErrors = $run->failed_count > 0;
    if ($run->status === 'done' && $hasImageErrors) {
        $state = '已完成，有异常';
    }
@endphp
<div class="admin-scan-progress" data-state="{{ $run->status }}" data-errors="{{ $hasImageErrors ? 'true' : 'false' }}" @if ($active) wire:poll.2s @endif>
    <div class="admin-scan-progress-heading">
        <strong>{{ $label }} · 任务 #{{ $run->id }}</strong>
        <span>{{ $state }}</span>
    </div>

    <progress class="admin-scan-progress-bar" max="100" value="{{ $percent }}" aria-label="{{ $label }}处理进度">{{ $percent }}%</progress>

    <div class="admin-scan-progress-counts">
        <span>已检查 <strong>{{ number_format($run->processed_count) }}</strong> 张
            @if ($run->total_count !== null && $run->status !== 'done') / {{ number_format($run->total_count) }} 张 @endif
        </span>
        <strong>{{ $percent }}%</strong>
    </div>
    <p class="admin-scan-progress-summary">成功 {{ number_format($run->successfulCount()) }} 张 · 异常 {{ number_format($run->failed_count ?? 0) }} 张</p>

    <div role="status" aria-live="polite" class="admin-scan-progress-status">
        @if ($run->status === 'done')
            <strong>{{ $hasImageErrors ? '本次检查已完成，部分图片存在异常' : '本次任务已完成' }}</strong>
            @if ($hasImageErrors)
                <p>异常图片已跳过，其余图片已继续检查。以下结果仅统计成功处理的图片。</p>
            @endif
            <p>{{ $run->result_count > 0 ? '发现 '.$run->result_count.' 对疑似重复图片，请查看对照后决定保留和删除。' : '未发现符合条件的疑似重复图片。' }}</p>
            <p>没有自动删除或归档任何图片。</p>
            @if ($run->total_count !== null && $run->processed_count !== $run->total_count)
                <p>提交时计划检查 {{ $run->total_count }} 张；处理期间删除或归档的图片会跳过。</p>
            @endif
            <p>关闭弹窗后可在下方列表查看结果。</p>
        @elseif ($run->status === 'failed')
            <strong>本次任务未完成</strong>
            <p>{{ $run->error_message ?: '处理失败，请检查后台处理服务和日志后重试。' }}</p>
            <p>任务已中断，已检查的进度和下方异常明细保留，未自动删除图片。</p>
        @elseif ($run->error_message)
            <p>{{ $run->error_message }}</p>
            <p>任务尚未完成，正在等待后台重试。</p>
        @elseif ($run->status === 'pending')
            <p>任务已提交，正在等待后台处理服务执行。</p>
        @elseif ($run->total_count !== null && $run->processed_count >= $run->total_count)
            <p>图片检查已结束，正在整理结果，请稍候。</p>
        @else
            <p>正在检查图片并更新匹配关系，进度每两秒自动刷新。</p>
        @endif
    </div>

    @if ($run->failures)
        <section class="admin-scan-errors" aria-label="异常图片明细">
            <h3>异常图片明细（{{ $run->failed_count }} 张）</h3>
            <ol>
                @foreach ($run->failures as $failure)
                    <li>
                        <div class="admin-scan-error-heading">
                            <strong>{{ $failure['title'] ?: ($failure['original_filename'] ?: ($failure['stored_filename'] ?: '未命名图片档案')) }}</strong>
                            <span>档案编号：{{ $failure['photo_id'] }}</span>
                        </div>
                        <p>原始文件名：{{ $failure['original_filename'] ?: '未记录' }}</p>
                        @if ($failure['stored_filename'])
                            <p>系统文件名：{{ $failure['stored_filename'] }}</p>
                        @endif
                        <p class="admin-scan-error-reason">原因：{{ $failure['reason'] }}</p>
                        @if (in_array($failure['photo_id'], $existingPhotoIds, true))
                            <a class="admin-scan-error-link" href="{{ \App\Filament\Resources\Photos\PhotoResource::getUrl('index', ['tableAction' => 'edit', 'tableActionRecord' => $failure['photo_id']]) }}" target="_blank" rel="noopener">查看图片档案</a>
                        @else
                            <p>该图片档案已删除，以上为检查时记录的信息。</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($active && ! $workerAvailable)
        <p class="admin-scan-progress-warning" role="alert">暂未检测到后台处理服务。任务不会自动开始，请启动或检查队列 Worker；服务恢复后继续执行，无需重复提交。</p>
    @endif
    @if ($active)
        <p class="admin-scan-progress-note">关闭弹窗不会取消任务，可通过“查看最近进度”重新打开。</p>
    @endif
</div>
