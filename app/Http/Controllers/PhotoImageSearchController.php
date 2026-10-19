<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPhotoImageSearch;
use App\Models\Photo;
use App\Models\PhotoImageSearch;
use App\Services\HomepageSettings;
use App\Services\PublicMediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PhotoImageSearchController extends Controller
{
    public function index(Request $request, HomepageSettings $settings, ?string $id = null): Response
    {
        $search = $id === null ? null : PhotoImageSearch::query()->whereKey($id)
            ->where('session_hash', $this->owner($request))->where('expires_at', '>', now())->firstOrFail();
        $matches = collect($search?->matches ?? []);
        $ids = $matches->pluck('photo_id')->all();
        // 查询完成后仍重新检查公开权限，避免结果缓存暴露后来下架的图片。
        $photos = Photo::query()->published()->whereNotIn('copyright_status', ['restricted', 'remove_requested'])
            ->whereIn('id', $ids)->get()->sortBy(fn ($photo) => array_search($photo->id, $ids, true))
            ->map(fn ($photo) => [
                'id' => $photo->id, 'title' => $photo->title, 'url' => '/photos/'.$photo->uuid,
                'image_url' => PublicMediaUrl::fromPublicDisk($photo->thumbnail_key ?: $photo->display_key),
            ])->values()->all();
        $state = $settings->formState();

        return Inertia::render('Search/Image', [
            'site' => [
                'name' => $state['site']['name'] ?? '梅西影像档案库',
                'logo_url' => PublicMediaUrl::fromPublicDisk($state['site']['logo_path'] ?? null),
                'search_placeholder' => $state['site']['search_placeholder'] ?? '搜索图片档案',
            ],
            'navigation' => collect($state['navigation']['items'] ?? [])->filter(fn ($item) => ($item['enabled'] ?? false) && filled($item['label'] ?? null))
                ->map(fn ($item) => ['label' => $item['label'], 'url' => $item['url']])->values()->all(),
            'imageSearch' => $search ? ['id' => $search->id, 'status' => $search->status, 'error_message' => $search->error_message] : null,
            'photos' => $photos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()?->isDisabled() || $request->user()?->isBanned(), 403);
        $request->validate([
            'image' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', function ($attribute, $value, $fail): void {
                $size = @getimagesize($value->getRealPath());
                if (! $size || $size[0] * $size[1] > 20000000) {
                    $fail('请选择不超过 2000 万像素的有效图片。');
                }
            }],
        ], ['image.max' => '图片大小不能超过 5 MB。', 'image.mimes' => '请选择 JPG、PNG 或 WebP 图片。']);
        $path = $request->file('image')->store('image-search', 'local');
        if (! $path) {
            throw ValidationException::withMessages(['image' => '图片暂存失败，请稍后重试。']);
        }
        $search = null;
        try {
            $search = PhotoImageSearch::query()->create([
                'id' => (string) Str::uuid(), 'session_hash' => $this->owner($request),
                'temporary_path' => $path, 'expires_at' => now()->addHour(),
            ]);
            ProcessPhotoImageSearch::dispatch($search->id);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            $search?->delete();
            report($exception);
            throw ValidationException::withMessages(['image' => '查询暂时无法提交，请稍后重试。']);
        }

        return redirect()->route('search.image.result', ['id' => $search->id], 303);
    }

    private function owner(Request $request): string
    {
        if (! $request->session()->has('image_search_owner')) {
            $request->session()->put('image_search_owner', (string) Str::uuid());
        }

        return hash('sha256', $request->session()->get('image_search_owner'));
    }
}
