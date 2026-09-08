<?php

namespace Tests\Feature;

use App\Filament\Resources\Opponents\OpponentResource;
use App\Models\Album;
use App\Models\Comment;
use App\Models\Opponent;
use App\Models\Photo;
use App\Models\PhotoFavorite;
use App\Models\PhotoLike;
use App\Models\PhotoShare;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicOpponentArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_opponents_and_attach_photos(): void
    {
        $this->actingAs(User::factory()->create());

        $opponent = Opponent::query()->create([
            'name' => 'France',
            'slug' => 'france',
            'country' => '法国',
            'aliases' => '法国队',
            'description' => '法国队相关影像。',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $photo = $this->publicPhoto('对手关联图片');

        $photo->opponents()->sync([$opponent->id]);

        $this->assertDatabaseHas('opponent_photo', [
            'opponent_id' => $opponent->id,
            'photo_id' => $photo->id,
        ]);
        $this->assertTrue($opponent->photos()->whereKey($photo->id)->exists());
        $this->get(OpponentResource::getUrl())->assertOk();
    }

    public function test_guest_can_visit_opponents_index_with_active_public_cards(): void
    {
        $argentina = $this->opponent('Argentina', ['country' => '阿根廷', 'aliases' => 'ARG']);
        $inactive = $this->opponent('Inactive Team', ['slug' => 'inactive-team', 'is_active' => false]);
        $empty = $this->opponent('Empty Team', ['slug' => 'empty-team']);
        $restrictedOnly = $this->opponent('Restricted Team', ['slug' => 'restricted-team']);

        $publicPhoto = $this->publicPhoto('阿根廷公开图片', [
            'event_date' => '2022-12-18',
            'display_key' => 'photos/display/argentina.webp',
        ]);
        $publicPhoto->opponents()->sync([$argentina->id]);

        $inactivePhoto = $this->publicPhoto('停用对手图片');
        $inactivePhoto->opponents()->sync([$inactive->id]);

        $restrictedPhoto = $this->publicPhoto('受限对手图片', ['copyright_status' => 'restricted']);
        $restrictedPhoto->opponents()->sync([$restrictedOnly->id]);

        $album = $this->publishedAlbum('阿根廷公开相册', ['cover_photo_id' => $publicPhoto->id]);
        $album->photos()->sync([$publicPhoto->id]);

        $this->get('/opponents?q=ARG')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Opponents/Index')
                ->where('archive.dimension.type', 'opponents')
                ->where('archive.dimension.path', '/opponents')
                ->where('archive.summary.opponents_count', 1)
                ->where('archive.summary.photos_count', 1)
                ->where('archive.summary.albums_count', 1)
                ->where('archive.filters.q', 'ARG')
                ->has('archive.opponents', 1)
                ->where('archive.opponents.0.name', 'Argentina')
                ->where('archive.opponents.0.url', '/opponents/argentina')
                ->where('archive.opponents.0.gallery_url', '/photos')
                ->where('archive.opponents.0.cover_image_url', '/storage/photos/display/argentina.webp')
                ->where('archive.opponents.0.public_photos_count', 1)
                ->where('archive.opponents.0.public_albums_count', 1)
            );

        $this->assertNotNull($empty->id);
        $this->assertNotNull($inactivePhoto->uuid);
        $this->assertNotNull($restrictedPhoto->uuid);
    }

    public function test_opponent_detail_filters_public_photos_albums_tags_people_and_years(): void
    {
        $opponent = $this->opponent('France', ['country' => '法国']);
        $honorTag = Tag::query()->create(['name' => '决赛', 'type' => '荣誉', 'sort_order' => 10]);
        $peopleTag = Tag::query()->create(['name' => '对手同框', 'type' => '人物关系', 'sort_order' => 20]);
        $user = User::factory()->create();

        $matching = $this->publicPhoto('World Cup final against France', [
            'description' => '决赛夜公开图片',
            'event_date' => '2022-12-18',
            'display_key' => 'photos/display/france-final.webp',
            'original_key' => 'private/original/france-final.jpg',
            'stored_filename' => 'secret-france-final.jpg',
            'published_at' => now()->subDay(),
        ]);
        $matching->opponents()->sync([$opponent->id]);
        $matching->tags()->sync([$honorTag->id, $peopleTag->id]);

        $wrongKeyword = $this->publicPhoto('训练图片', ['event_date' => '2022-12-19']);
        $wrongKeyword->opponents()->sync([$opponent->id]);
        $wrongKeyword->tags()->sync([$honorTag->id, $peopleTag->id]);

        $wrongYear = $this->publicPhoto('World Cup next year', ['event_date' => '2023-01-01']);
        $wrongYear->opponents()->sync([$opponent->id]);
        $wrongYear->tags()->sync([$honorTag->id, $peopleTag->id]);

        $restricted = $this->publicPhoto('World Cup restricted', ['copyright_status' => 'restricted', 'event_date' => '2022-12-18']);
        $restricted->opponents()->sync([$opponent->id]);
        $restricted->tags()->sync([$honorTag->id, $peopleTag->id]);

        $album = $this->publishedAlbum('法国公开相册', ['slug' => 'france-public-album', 'cover_photo_id' => $matching->id]);
        $album->photos()->sync([$matching->id, $restricted->id]);

        PhotoFavorite::query()->create(['photo_id' => $matching->id, 'user_id' => $user->id]);
        PhotoLike::query()->create(['photo_id' => $matching->id, 'user_id' => $user->id]);
        PhotoShare::query()->create([
            'photo_id' => $matching->id,
            'user_id' => $user->id,
            'channel' => 'copy_link',
            'page_url' => 'http://127.0.0.1/photos/'.$matching->uuid,
        ]);
        Comment::query()->create([
            'photo_id' => $matching->id,
            'user_id' => $user->id,
            'type' => 'discussion',
            'content' => '公开评论',
            'status' => 'published',
        ]);

        $query = http_build_query([
            'q' => 'World Cup',
            'years' => [2022],
            'tags' => [$honorTag->id],
            'people_tags' => [$peopleTag->id],
            'sort' => 'hot_desc',
        ]);

        $this->get('/opponents/france?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Opponents/Show')
                ->where('archive.dimension.type', 'opponents')
                ->where('archive.dimension.index_url', '/opponents')
                ->where('archive.dimension.gallery_url', '/photos')
                ->where('archive.opponent.name', 'France')
                ->where('archive.filters.q', 'World Cup')
                ->where('archive.filters.years.0', 2022)
                ->where('archive.filters.tags.0', $honorTag->id)
                ->where('archive.filters.people_tags.0', $peopleTag->id)
                ->where('archive.filters.sort', 'hot_desc')
                ->where('archive.summary.photos_count', 1)
                ->where('archive.summary.albums_count', 1)
                ->where('archive.summary.years_count', 2)
                ->has('archive.photos.data', 1)
                ->where('archive.photos.data.0.title', 'World Cup final against France')
                ->where('archive.photos.data.0.image_url', '/storage/photos/display/france-final.webp')
                ->missing('archive.photos.data.0.original_key')
                ->missing('archive.photos.data.0.stored_filename')
                ->has('archive.albums', 1)
                ->where('archive.albums.0.title', '法国公开相册')
                ->where('archive.albums.0.public_photos_count', 1)
            );

        $this->assertNotNull($wrongKeyword->uuid);
        $this->assertNotNull($wrongYear->uuid);
    }

    public function test_hidden_empty_and_missing_opponents_are_not_publicly_visible(): void
    {
        $inactive = $this->opponent('Inactive', ['slug' => 'inactive', 'is_active' => false]);
        $empty = $this->opponent('Empty', ['slug' => 'empty']);
        $restrictedOnly = $this->opponent('Restricted', ['slug' => 'restricted']);
        $restricted = $this->publicPhoto('受限图片', ['copyright_status' => 'restricted']);
        $restricted->opponents()->sync([$restrictedOnly->id]);

        $this->get('/opponents/inactive')->assertNotFound();
        $this->get('/opponents/empty')->assertNotFound();
        $this->get('/opponents/restricted')->assertNotFound();
        $this->get('/opponents/missing')->assertNotFound();

        $this->assertNotNull($inactive->id);
        $this->assertNotNull($empty->id);
    }

    public function test_sitemap_lists_opponents_with_public_content(): void
    {
        $public = $this->opponent('Netherlands', ['slug' => 'netherlands']);
        $inactive = $this->opponent('Inactive', ['slug' => 'inactive', 'is_active' => false]);
        $empty = $this->opponent('Empty', ['slug' => 'empty']);

        $photo = $this->publicPhoto('荷兰 sitemap 图片');
        $photo->opponents()->sync([$public->id]);

        $inactivePhoto = $this->publicPhoto('停用 sitemap 图片');
        $inactivePhoto->opponents()->sync([$inactive->id]);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('http://localhost/opponents', $content);
        $this->assertStringContainsString('http://localhost/opponents/netherlands', $content);
        $this->assertStringNotContainsString('http://localhost/opponents/inactive', $content);
        $this->assertStringNotContainsString('http://localhost/opponents/empty', $content);

        $this->assertNotNull($empty->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function opponent(string $name, array $attributes = []): Opponent
    {
        return Opponent::query()->create(array_merge([
            'name' => $name,
            'slug' => Str::slug($name),
            'country' => null,
            'aliases' => null,
            'description' => $name.'相关资料。',
            'sort_order' => 10,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publicPhoto(string $title, array $attributes = []): Photo
    {
        return Photo::query()->create(array_merge([
            'title' => $title,
            'status' => 'published',
            'copyright_status' => 'unknown',
            'event_date' => '2022-12-18',
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
}