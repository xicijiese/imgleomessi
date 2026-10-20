<div class="space-y-4">
    @if (! $submission->cleaned_at)
        <img src="{{ url('/me/submissions/'.$submission->id.'/image') }}" alt="投稿原图" style="max-width:100%;max-height:60vh;object-fit:contain" />
    @else
        <p>暂存文件已清理，审核记录保留。</p>
    @endif
    <p>尺寸：{{ $submission->width }} × {{ $submission->height }}；大小：{{ number_format($submission->file_size / 1024) }} KB</p>
    <p>来源：{{ $submission->metadata['source_note'] ?? '未填写' }}</p>
    @if (!empty($submission->metadata['source_url']))
        <a href="{{ $submission->metadata['source_url'] }}" target="_blank" rel="noopener noreferrer">打开来源链接</a>
    @endif
    <p>说明：{{ $submission->metadata['description'] ?? '无' }}</p>
    <p>内部备注：{{ $submission->internal_note ?? '无' }}</p>
    <h3>相似档案（仅供审核参考）</h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
        @foreach (\App\Models\Photo::query()->whereIn('id', $submission->similar_photo_ids ?? [])->get() as $photo)
            <div style="width:150px">
                <img src="{{ \App\Services\PublicMediaUrl::fromPublicDisk($photo->thumbnail_key) }}" alt="{{ $photo->title }}" style="width:150px;height:110px;object-fit:contain" />
                <p>#{{ $photo->id }} {{ $photo->title }}</p>
            </div>
        @endforeach
    </div>
    <h3>操作记录</h3>
    @foreach ($events as $event)
        <p>{{ $event->created_at->timezone('Asia/Shanghai')->format('Y-m-d H:i:s') }} · {{ \App\Models\SubmissionEvent::ACTIONS[$event->action] ?? $event->action }} · {{ $event->note }}</p>
    @endforeach
</div>
