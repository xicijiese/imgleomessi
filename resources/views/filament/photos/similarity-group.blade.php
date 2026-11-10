<div class="admin-comparison-grid">
    @foreach ($photos as $photo)
        <a href="{{ url('/admin/photos') }}?search={{ urlencode($photo->title) }}" class="admin-comparison-card" target="_blank" rel="noopener">
            <div class="admin-comparison-media">
                @if ($photo->display_key || $photo->thumbnail_key)
                    <img src="{{ app(\App\Services\PhotoStorage::class)->url($photo->display_key ?: $photo->thumbnail_key) }}" alt="{{ $photo->title }}" loading="lazy">
                @endif
            </div>
            <p class="admin-comparison-title">#{{ $photo->id }} {{ $photo->title }}</p>
            <p class="admin-comparison-status">{{ \App\Models\Photo::STATUSES[$photo->status] ?? $photo->status }}</p>
            <p class="admin-comparison-status">{{ $photo->width ?? '—' }} × {{ $photo->height ?? '—' }} · {{ $photo->file_size ? number_format($photo->file_size / 1024, 1).' KB' : '大小待补充' }} · 点击查看图片档案</p>
        </a>
    @endforeach
</div>
