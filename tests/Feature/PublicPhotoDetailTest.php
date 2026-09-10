<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Tag;
use App\Services\HomepageSettings;
use Database\Seeders\GalleryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPhotoDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_public_photo_detail_with_metadata(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $sourceUrl = 'https://example.com/messi-photo';
        $tag = Tag::query()->create([
            'name' => '捧杯',
            'sort_order' => 10,
        ]);
        $photo = $this->publicPhoto('世界杯决赛捧杯', [
            'description' => '梅西在世界杯决赛后捧起奖杯。',
            'source_url' => $sourceUrl,
            'copyright_status' => 'credited',
            'display_key' => 'photos/display/world-cup.webp',
            'thumbnail_key' => 'photos/thumb/world-cup.webp',
            'original_key' => 'photos/original/world-cup.jpg',
            'stored_filename' => 'secret-world-cup.jpg',
            'taken_at' => '2022-12-18 20:30:00',
            'event_date' => '2022-12-18',
            'width' => 2400,
            'height' => 1600,
            'mime_type' => 'image/jpeg',
            'file_size' => 2048000,
        ]);
        $photo->categories()->sync($this->pendingCategoryIds());
        $photo->tags()->sync([$tag->id]);

        $album = $this->publishedAlbum('2022 世界杯决赛', ['slug' => 'world-cup-final']);
        $album->photos()->sync([$photo->id]);

        $this->get('/photos/'.$photo->uuid)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Show')
                ->where('photoDetail.site.name', '梅西影像档案库')
                ->where('photoDetail.photo.title', '世界杯决赛捧杯')
                ->where('photoDetail.photo.description', '梅西在世界杯决赛后捧起奖杯。')
                ->where('photoDetail.photo.image_url', '/storage/photos/display/world-cup.webp')
                ->where('photoDetail.photo.alt', '世界杯决赛捧杯')
                ->where('photoDetail.photo.event_date', '2022-12-18')
                ->where('photoDetail.photo.taken_at', '2022-12-18 20:30')
                ->where('photoDetail.photo.width', 2400)
                ->where('photoDetail.photo.height', 1600)
                ->where('photoDetail.photo.mime_type', 'image/jpeg')
                ->where('photoDetail.photo.file_size_label', '2 MB')
                ->where('photoDetail.photo.copyright_status.label', '已标注来源')
                ->where('photoDetail.photo.source.source_url', 'https://example.com/messi-photo')
                ->missing('photoDetail.photo.original_key')
                ->missing('photoDetail.photo.stored_filename')
                ->has('photoDetail.photo.categories', 8)
                ->where('photoDetail.photo.tags.0.name', '捧杯')
                ->where('photoDetail.photo.albums.0.url', '/albums/world-cup-final')
                ->where('photoDetail.context.type', 'gallery')
                ->where('photoDetail.context.return_url', '/photos')
            );
    }

    public function test_photo_detail_rejects_missing_and_non_public_photos(): void
    {
        $draft = $this->publicPhoto('草稿图', ['status' => 'draft']);
        $archived = $this->publicPhoto('归档图', ['status' => 'archived']);
        $restricted = $this->publicPhoto('受限图', ['copyright_status' => 'restricted']);
        $removeRequested = $this->publicPhoto('请求下架图', ['copyright_status' => 'remove_requested']);

        $this->get('/photos/'.$draft->uuid)->assertNotFound();
        $this->get('/photos/'.$archived->uuid)->assertNotFound();
        $this->get('/photos/'.$restricted->uuid)->assertNotFound();
        $this->get('/photos/'.$removeRequested->uuid)->assertNotFound();
        $this->get('/photos/'.Str::uuid())->assertNotFound();
    }

    public function test_album_context_controls_return_and_adjacent_photos(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $previous = $this->publicPhoto('前一张', ['event_date' => '2022-12-20']);
        $current = $this->publicPhoto('当前图', ['event_date' => '2022-12-18']);
        $next = $this->publicPhoto('后一张', ['event_date' => '2022-12-16']);
        $outside = $this->publicPhoto('相册外图', ['event_date' => '2022-12-22']);

        $album = $this->publishedAlbum('上下文相册', ['slug' => 'context-album']);
        $album->photos()->sync([$previous->id, $current->id, $next->id]);
        $invalidAlbum = $this->publishedAlbum('无效相册', ['slug' => 'invalid-album']);
        $invalidAlbum->photos()->sync([$outside->id]);

        $this->get('/photos/'.$current->uuid.'?album=context-album')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Show')
                ->where('photoDetail.context.type', 'album')
                ->where('photoDetail.context.title', '上下文相册')
                ->where('photoDetail.context.return_url', '/albums/context-album')
                ->where('photoDetail.adjacent.previous.title', '前一张')
                ->where('photoDetail.adjacent.next.title', '后一张')
                ->has('photoDetail.related.data', 2)
            );

        $this->get('/photos/'.$current->uuid.'?album=invalid-album')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Show')
                ->where('photoDetail.context.type', 'gallery')
                ->where('photoDetail.context.return_url', '/photos')
                ->where('photoDetail.adjacent.previous', null)
                ->where('photoDetail.adjacent.next', null)
            );
    }

    public function test_topic_context_uses_configured_photo_order(): void
    {
        $previous = $this->publicPhoto('专题前一张');
        $current = $this->publicPhoto('专题当前图');
        $next = $this->publicPhoto('专题后一张');
        $outside = $this->publicPhoto('专题外图');

        $this->saveTopicModule([
            'items' => [
                [
                    'enabled' => true,
                    'title' => '世界杯专题',
                    'url' => '/topics/world-cup',
                    'photo_ids' => [$previous->id, $current->id, $next->id],
                ],
                [
                    'enabled' => true,
                    'title' => '无效专题',
                    'url' => '/topics/invalid-topic',
                    'photo_ids' => [$outside->id],
                ],
            ],
        ]);

        $this->get('/photos/'.$current->uuid.'?topic=world-cup')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Show')
                ->where('photoDetail.context.type', 'topic')
                ->where('photoDetail.context.title', '世界杯专题')
                ->where('photoDetail.context.return_url', '/topics/world-cup')
                ->where('photoDetail.adjacent.previous.title', '专题前一张')
                ->where('photoDetail.adjacent.next.title', '专题后一张')
            );

        $this->get('/photos/'.$current->uuid.'?topic=invalid-topic')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Show')
                ->where('photoDetail.context.type', 'gallery')
                ->where('photoDetail.adjacent.previous', null)
                ->where('photoDetail.adjacent.next', null)
            );
    }

    public function test_missing_source_url_is_not_exposed(): void
    {
        $photo = $this->publicPhoto('无来源图片');

        $this->get('/photos/'.$photo->uuid)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Show')
                ->where('photoDetail.photo.source', null)
            );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publicPhoto(string $title, array $attributes = []): Photo
    {
        return Photo::query()->create(array_merge([
            'title' => $title,
            'status' => 'published',
            'published_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedAlbum(string $title, array $attributes = []): Album
    {
        return Album::query()->create(array_merge([
            'title' => $title,
            'slug' => 'album-'.Str::uuid(),
            'status' => 'published',
            'published_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function saveTopicModule(array $overrides): void
    {
        $defaults = HomepageSettings::defaults()['topic_module'];

        Setting::setValue('home', 'topic_module', array_replace_recursive($defaults, $overrides));
    }

    /**
     * @return array<int, int>
     */
    private function pendingCategoryIds(): array
    {
        return Category::query()
            ->children()
            ->where('name', '待补充')
            ->pluck('id')
            ->all();
    }
}
