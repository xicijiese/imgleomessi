<div class="admin-submission-preview">
    <div class="admin-submission-main">
        @if (! $submission->cleaned_at)
            <img src="{{ url('/me/submissions/'.$submission->id.'/image') }}" alt="投稿原图" class="admin-submission-image" />
        @else
            <p>暂存文件已清理，审核记录保留。</p>
        @endif
    </div>
    <div class="admin-submission-metadata">
        <dl>
            <div><dt>尺寸</dt><dd>{{ $submission->width }} × {{ $submission->height }}</dd></div>
            <div><dt>大小</dt><dd>{{ number_format($submission->file_size / 1024) }} KB</dd></div>
            <div><dt>来源</dt><dd>{{ $submission->metadata['source_note'] ?? '未填写' }}</dd></div>
            <div><dt>说明</dt><dd>{{ $submission->metadata['description'] ?? '无' }}</dd></div>
            <div><dt>内部备注</dt><dd>{{ $submission->internal_note ?? '无' }}</dd></div>
        </dl>
        @if (!empty($submission->metadata['source_url']))
            <a href="{{ $submission->metadata['source_url'] }}" target="_blank" rel="noopener noreferrer" class="admin-review-source-link">打开来源链接</a>
        @endif
    </div>
    <section class="admin-submission-wide">
        <h3 class="admin-review-heading">相似档案（仅供审核参考）</h3>
        <div class="admin-review-related">
            @foreach (\App\Models\Photo::query()->whereIn('id', $submission->similar_photo_ids ?? [])->get() as $photo)
                <div>
                    <img src="{{ \App\Services\PublicMediaUrl::fromPublicDisk($photo->thumbnail_key) }}" alt="{{ $photo->title }}" />
                    <p>#{{ $photo->id }} {{ $photo->title }}</p>
                </div>
            @endforeach
        </div>
    </section>
    <section class="admin-submission-wide">
        <h3 class="admin-review-heading">操作记录</h3>
        <div class="admin-review-events">
            @foreach ($events as $event)
                <div class="admin-review-event">
                    <time>{{ $event->created_at->timezone('Asia/Shanghai')->format('Y-m-d H:i:s') }}</time>
                    <div><p class="admin-review-event-action">{{ \App\Models\SubmissionEvent::ACTIONS[$event->action] ?? $event->action }}</p><p class="admin-review-note">{{ $event->note }}</p></div>
                </div>
            @endforeach
        </div>
    </section>
</div>
