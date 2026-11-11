<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\ShareLink;
use App\Services\PublicMediaUrl;
use App\Services\SharePromotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class WordPressAlbumController extends Controller
{
    public function resolve(Request $request, SharePromotion $sharing)
    {
        $data = $request->validate(['url' => 'required|url:http,https|max:2048']);
        $parts = parse_url($data['url']);
        $origin = parse_url(config('app.url'));
        abort_unless(strtolower($parts['host'] ?? '') === strtolower($origin['host'] ?? '')
            && ($parts['scheme'] ?? '') === ($origin['scheme'] ?? '')
            && ($parts['port'] ?? null) === ($origin['port'] ?? null)
            && ! isset($parts['user']) && ! isset($parts['pass']), 422, '请粘贴本站相册分享链接。');
        $path = rawurldecode($parts['path'] ?? '');
        if (preg_match('~^/s/([A-Za-z0-9]{1,32})/?$~D', $path, $matches)) {
            $link = ShareLink::where('code', $matches[1])->firstOrFail();
            abort_unless($link->target_type === 'album', 422, '请选择“整个相册”后复制链接。');
            $sharing->available($link);
            $slug = $link->target_key;
        } elseif (preg_match('~^/albums/([^/]+)/?$~D', $path, $matches)) {
            $slug = $matches[1];
        } else {
            abort(422, '请选择“整个相册”后复制链接。');
        }
        $album = Album::published()->where('slug', $slug)->firstOrFail();

        return response()->json(['album' => $this->header($album)]);
    }

    public function show(Request $request, int $album)
    {
        $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $record = Album::published()->findOrFail($album);
        $query = $record->photos()->where('photos.status', 'published')
            ->whereNotIn('photos.copyright_status', ['restricted', 'remove_requested'])
            ->whereNotNull('photos.display_key')->whereNotNull('photos.thumbnail_key')
            ->where('photos.display_key', '<>', '')->where('photos.thumbnail_key', '<>', '')
            ->whereColumn('photos.display_key', '<>', 'photos.original_key')
            ->whereColumn('photos.thumbnail_key', '<>', 'photos.original_key')
            ->whereColumn('photos.thumbnail_key', '<>', 'photos.display_key');
        $revision = hash('sha256', $record->updated_at.'|'.$query->clone()->orderBy('photos.id')->get(['photos.uuid', 'photos.updated_at'])->toJson());
        $page = $query->orderBy('photos.id')->paginate($request->integer('per_page', 100));
        $photos = $page->getCollection()->map(function ($photo): array {
            return [
                'id' => $photo->uuid,
                'title' => $photo->title,
                'caption' => $photo->description ?? '',
                'alt' => $photo->title,
                'thumbnail_url' => URL::to(PublicMediaUrl::thumbnail($photo)),
                'display_url' => URL::to(PublicMediaUrl::display($photo)),
                'url' => route('photos.show', $photo->uuid),
                'width' => $photo->width,
                'height' => $photo->height,
            ];
        });

        return response()->json([
            'album' => $this->header($record), 'revision' => $revision, 'photos' => $photos,
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    private function header(Album $album): array
    {
        return ['id' => $album->id, 'title' => $album->title, 'url' => route('albums.show', $album->slug)];
    }
}
