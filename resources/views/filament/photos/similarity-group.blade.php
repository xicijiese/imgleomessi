<div style="display: flex; flex-wrap: wrap; gap: 16px;">
    @foreach ($photos as $photo)
        <a href="{{ url('/admin/photos') }}?tableSearch={{ urlencode($photo->title) }}" style="display: block; flex: 1 1 240px; min-width: 0;" target="_blank" rel="noopener">
            @if ($photo->thumbnail_key)
                <img src="{{ \App\Services\PublicMediaUrl::fromPublicDisk($photo->thumbnail_key) }}" alt="{{ $photo->title }}" style="width: 100%; height: 240px; border-radius: 8px; object-fit: contain;" loading="lazy">
            @endif
            <p style="margin-top: 8px; font-size: 14px; overflow-wrap: anywhere;">#{{ $photo->id }} {{ $photo->title }}</p>
            <p style="font-size: 12px;">{{ \App\Models\Photo::STATUSES[$photo->status] ?? $photo->status }}</p>
        </a>
    @endforeach
</div>
