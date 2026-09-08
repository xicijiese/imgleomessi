<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\PhotoFavorite;
use App\Models\PhotoLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPhotoInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_detail_exposes_interaction_state_and_like_count(): void
    {
        $photo = $this->publicPhoto('互动状态图片');
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        PhotoLike::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $otherUser->id,
        ]);

        $this->get('/photos/'.$photo->uuid)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Show')
                ->where('photoDetail.photo.interactions.can_interact', false)
                ->where('photoDetail.photo.interactions.is_favorited', false)
                ->where('photoDetail.photo.interactions.is_liked', false)
                ->where('photoDetail.photo.interactions.likes_count', 1)
            );

        PhotoFavorite::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);
        PhotoLike::query()->create([
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/photos/'.$photo->uuid)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('photoDetail.photo.interactions.can_interact', true)
                ->where('photoDetail.photo.interactions.is_favorited', true)
                ->where('photoDetail.photo.interactions.is_liked', true)
                ->where('photoDetail.photo.interactions.likes_count', 2)
            );
    }

    public function test_guest_favorite_and_like_redirect_to_login(): void
    {
        $photo = $this->publicPhoto('登录后互动图片');

        $this->post('/photos/'.$photo->uuid.'/favorite')
            ->assertRedirect(route('login'));

        $this->post('/photos/'.$photo->uuid.'/like')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_favorite_and_unfavorite_once(): void
    {
        $photo = $this->publicPhoto('收藏图片');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/photos/'.$photo->uuid.'/favorite')
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/photos/'.$photo->uuid.'/favorite')
            ->assertRedirect();

        $this->assertDatabaseCount('photo_favorites', 1);
        $this->assertDatabaseHas('photo_favorites', [
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete('/photos/'.$photo->uuid.'/favorite')
            ->assertRedirect();

        $this->assertDatabaseMissing('photo_favorites', [
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_authenticated_user_can_like_and_unlike_once(): void
    {
        $photo = $this->publicPhoto('点赞图片');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/photos/'.$photo->uuid.'/like')
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/photos/'.$photo->uuid.'/like')
            ->assertRedirect();

        $this->assertDatabaseCount('photo_likes', 1);
        $this->assertDatabaseHas('photo_likes', [
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete('/photos/'.$photo->uuid.'/like')
            ->assertRedirect();

        $this->assertDatabaseMissing('photo_likes', [
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_guest_can_create_share_record(): void
    {
        $photo = $this->publicPhoto('分享图片');

        $this->post('/photos/'.$photo->uuid.'/shares', [
            'channel' => 'copy_link',
            'page_url' => 'http://127.0.0.1:8000/photos/'.$photo->uuid,
        ])->assertRedirect();

        $this->assertDatabaseHas('photo_shares', [
            'photo_id' => $photo->id,
            'user_id' => null,
            'channel' => 'copy_link',
            'page_url' => 'http://127.0.0.1:8000/photos/'.$photo->uuid,
        ]);
    }

    public function test_authenticated_user_can_favorite_and_unfavorite_with_json_payload(): void
    {
        $photo = $this->publicPhoto('JSON 收藏图片');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/photos/'.$photo->uuid.'/favorite')
            ->assertOk()
            ->assertJson([
                'is_favorited' => true,
            ]);

        $this->assertDatabaseHas('photo_favorites', [
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson('/photos/'.$photo->uuid.'/favorite')
            ->assertOk()
            ->assertJson([
                'is_favorited' => false,
            ]);

        $this->assertDatabaseMissing('photo_favorites', [
            'photo_id' => $photo->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_authenticated_user_can_like_and_unlike_with_json_payload(): void
    {
        $photo = $this->publicPhoto('JSON 点赞图片');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/photos/'.$photo->uuid.'/like')
            ->assertOk()
            ->assertJson([
                'is_liked' => true,
                'likes_count' => 1,
            ]);

        $this->actingAs($user)
            ->deleteJson('/photos/'.$photo->uuid.'/like')
            ->assertOk()
            ->assertJson([
                'is_liked' => false,
                'likes_count' => 0,
            ]);
    }

    public function test_guest_can_create_share_record_with_json_payload(): void
    {
        $photo = $this->publicPhoto('JSON 分享图片');

        $this->postJson('/photos/'.$photo->uuid.'/shares', [
            'channel' => 'copy_link',
            'page_url' => 'http://127.0.0.1:8000/photos/'.$photo->uuid,
        ])
            ->assertOk()
            ->assertJson([
                'recorded' => true,
            ]);

        $this->assertDatabaseHas('photo_shares', [
            'photo_id' => $photo->id,
            'user_id' => null,
            'channel' => 'copy_link',
            'page_url' => 'http://127.0.0.1:8000/photos/'.$photo->uuid,
        ]);
    }
    public function test_guest_can_record_all_p1_14_share_channels(): void
    {
        $photo = $this->publicPhoto('P1-14 分享渠道图片');

        foreach (['copy_link', 'native_share', 'weibo', 'wechat_qr'] as $channel) {
            $this->postJson('/photos/'.$photo->uuid.'/shares', [
                'channel' => $channel,
                'page_url' => 'http://127.0.0.1:8000/photos/'.$photo->uuid,
            ])
                ->assertOk()
                ->assertJson(['recorded' => true]);

            $this->assertDatabaseHas('photo_shares', [
                'photo_id' => $photo->id,
                'channel' => $channel,
            ]);
        }
    }

    public function test_share_record_rejects_unknown_channel(): void
    {
        $photo = $this->publicPhoto('非法分享渠道图片');

        $this->postJson('/photos/'.$photo->uuid.'/shares', [
            'channel' => 'poster',
            'page_url' => 'http://127.0.0.1:8000/photos/'.$photo->uuid,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('photo_shares', 0);
    }

    public function test_non_public_photos_cannot_be_interacted_with(): void
    {
        $draft = $this->publicPhoto('草稿互动图片', ['status' => 'draft']);
        $restricted = $this->publicPhoto('受限互动图片', ['copyright_status' => 'restricted']);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/photos/'.$draft->uuid.'/favorite')->assertNotFound();
        $this->actingAs($user)->post('/photos/'.$draft->uuid.'/like')->assertNotFound();
        $this->post('/photos/'.$draft->uuid.'/shares', ['channel' => 'copy_link'])->assertNotFound();
        $this->actingAs($user)->post('/photos/'.$restricted->uuid.'/favorite')->assertNotFound();
        $this->actingAs($user)->post('/photos/'.$restricted->uuid.'/like')->assertNotFound();
        $this->post('/photos/'.$restricted->uuid.'/shares', ['channel' => 'copy_link'])->assertNotFound();

        $this->assertDatabaseCount('photo_favorites', 0);
        $this->assertDatabaseCount('photo_likes', 0);
        $this->assertDatabaseCount('photo_shares', 0);
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
}
