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
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicTopicDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_topic_detail_with_configured_albums_and_photos(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $cover = $this->publicPhoto('相册封面', [
            'display_key' => 'photos/display/album-cover.webp',
        ]);
        $album = $this->publishedAlbum('金球奖相册', [
            'slug' => 'ballon-dor-album',
            'description' => '金球奖领奖图片',
            'cover_photo_id' => $cover->id,
        ]);
        $album->categories()->sync($this->pendingCategoryIds());
        $album->photos()->sync([$cover->id]);

        $tag = Tag::query()->create([
            'name' => '获奖',
            'sort_order' => 10,
        ]);
        $photo = $this->publicPhoto('金球奖精选图', [
            'display_key' => 'photos/display/topic-photo.webp',
            'event_date' => '2023-10-30',
        ]);
        $photo->categories()->sync($this->pendingCategoryIds());
        $photo->tags()->sync([$tag->id]);

        $this->saveTopicModule([
            'items' => [[
                'enabled' => true,
                'title' => '金球奖',
                'url' => '/topics/ballon-dor',
                'cover_image_path' => 'settings/topics/ballon-dor.webp',
                'description' => '梅西金球奖影像专题',
                'album_ids' => [$album->id],
                'photo_ids' => [$photo->id],
            ]],
        ]);

        $this->get('/topics/ballon-dor')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Topics/Show')
                ->where('topicDetail.site.name', '梅西影像档案库')
                ->where('topicDetail.topic.title', '金球奖')
                ->where('topicDetail.topic.slug', 'ballon-dor')
                ->where('topicDetail.topic.description', '梅西金球奖影像专题')
                ->where('topicDetail.topic.cover_image_url', '/storage/settings/topics/ballon-dor.webp')
                ->has('topicDetail.albums.data', 1)
                ->where('topicDetail.albums.data.0.title', '金球奖相册')
                ->where('topicDetail.albums.data.0.url', '/albums/ballon-dor-album')
                ->where('topicDetail.albums.data.0.public_photos_count', 1)
                ->where('topicDetail.albums.data.0.cover_image_url', '/storage/photos/display/album-cover.webp')
                ->has('topicDetail.photos.data', 1)
                ->where('topicDetail.photos.data.0.title', '金球奖精选图')
                ->where('topicDetail.photos.data.0.url', '/photos/'.$photo->uuid.'?topic=ballon-dor')
                ->where('topicDetail.photos.data.0.image_url', '/storage/photos/display/topic-photo.webp')
                ->where('topicDetail.photos.data.0.tags.0.name', '获奖')
            );
    }

    public function test_topic_detail_rejects_missing_disabled_blank_and_mismatched_topics(): void
    {
        $this->saveTopicModule([
            'items' => [
                [
                    'enabled' => false,
                    'title' => '关闭专题',
                    'url' => '/topics/disabled',
                ],
                [
                    'enabled' => true,
                    'title' => '',
                    'url' => '/topics/blank-title',
                ],
                [
                    'enabled' => true,
                    'title' => '错位专题',
                    'url' => '/topics/other-slug',
                ],
            ],
        ]);

        $this->get('/topics/disabled')->assertNotFound();
        $this->get('/topics/blank-title')->assertNotFound();
        $this->get('/topics/mismatched')->assertNotFound();
        $this->get('/topics/missing')->assertNotFound();
    }

    public function test_topic_detail_filters_non_public_albums_and_photos_while_preserving_configured_order(): void
    {
        $validAlbumFirst = $this->publishedAlbum('第二个显示的公开相册');
        $validAlbumFirst->photos()->sync([$this->publicPhoto('公开相册图一')->id]);

        $validAlbumSecond = $this->publishedAlbum('第一个显示的公开相册');
        $validAlbumSecond->photos()->sync([$this->publicPhoto('公开相册图二')->id]);

        $draftAlbum = $this->publishedAlbum('草稿相册', ['status' => 'draft']);
        $draftAlbum->photos()->sync([$this->publicPhoto('草稿相册图')->id]);

        $hiddenAlbum = $this->publishedAlbum('隐藏相册', ['status' => 'hidden']);
        $hiddenAlbum->photos()->sync([$this->publicPhoto('隐藏相册图')->id]);

        $emptyAlbum = $this->publishedAlbum('空相册');

        $restrictedAlbum = $this->publishedAlbum('受限相册');
        $restrictedAlbum->photos()->sync([$this->publicPhoto('受限相册图', ['copyright_status' => 'restricted'])->id]);

        $validPhotoFirst = $this->publicPhoto('第二张显示的公开图');
        $validPhotoSecond = $this->publicPhoto('第一张显示的公开图');
        $draftPhoto = $this->publicPhoto('草稿图', ['status' => 'draft']);
        $archivedPhoto = $this->publicPhoto('归档图', ['status' => 'archived']);
        $restrictedPhoto = $this->publicPhoto('受限图', ['copyright_status' => 'restricted']);
        $removeRequestedPhoto = $this->publicPhoto('请求下架图', ['copyright_status' => 'remove_requested']);

        $this->saveTopicModule([
            'items' => [[
                'enabled' => true,
                'title' => '世界杯',
                'url' => '/topics/world-cup',
                'album_ids' => [
                    $validAlbumSecond->id,
                    $draftAlbum->id,
                    $hiddenAlbum->id,
                    $emptyAlbum->id,
                    $restrictedAlbum->id,
                    $validAlbumFirst->id,
                ],
                'photo_ids' => [
                    $validPhotoSecond->id,
                    $draftPhoto->id,
                    $archivedPhoto->id,
                    $restrictedPhoto->id,
                    $removeRequestedPhoto->id,
                    $validPhotoFirst->id,
                ],
            ]],
        ]);

        $this->get('/topics/world-cup')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Topics/Show')
                ->has('topicDetail.albums.data', 2)
                ->where('topicDetail.albums.data.0.title', '第一个显示的公开相册')
                ->where('topicDetail.albums.data.1.title', '第二个显示的公开相册')
                ->has('topicDetail.photos.data', 2)
                ->where('topicDetail.photos.data.0.title', '第一张显示的公开图')
                ->where('topicDetail.photos.data.1.title', '第二张显示的公开图')
            );
    }

    public function test_topic_detail_existing_topic_without_content_returns_ok(): void
    {
        $this->saveTopicModule([
            'items' => [[
                'enabled' => true,
                'title' => '空专题',
                'url' => '/topics/empty-topic',
                'description' => '',
                'album_ids' => [],
                'photo_ids' => [],
            ]],
        ]);

        $this->get('/topics/empty-topic')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Topics/Show')
                ->where('topicDetail.topic.title', '空专题')
                ->where('topicDetail.topic.description', null)
                ->has('topicDetail.albums.data', 0)
                ->has('topicDetail.photos.data', 0)
            );
    }

    public function test_homepage_settings_rejects_non_public_topic_detail_content(): void
    {
        $draftPhoto = $this->publicPhoto('草稿精选图', ['status' => 'draft']);
        $emptyAlbum = $this->publishedAlbum('空相册');

        $state = HomepageSettings::defaults();
        $state['topic_module']['items'] = [[
            'enabled' => true,
            'title' => '异常专题',
            'url' => '/topics/invalid-content',
            'photo_ids' => [$draftPhoto->id],
            'album_ids' => [$emptyAlbum->id],
        ]];

        $this->expectException(ValidationException::class);

        app(HomepageSettings::class)->save($state);
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
