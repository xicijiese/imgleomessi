<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPhotoCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_detail_only_exposes_published_discussion_comments(): void
    {
        $photo = $this->publicPhoto('有评论的图片');
        $user = User::factory()->create(['name' => '测试用户甲']);
        $older = $this->comment($photo, $user, '较早公开评论', [
            'status' => 'published',
            'created_at' => now()->subMinutes(10),
        ]);
        $newer = $this->comment($photo, $user, '较新公开评论', [
            'status' => 'published',
            'created_at' => now()->subMinute(),
        ]);
        $pending = $this->comment($photo, $user, '待审核评论', ['status' => 'pending']);
        $correction = $this->comment($photo, $user, '公开纠错不进评论流', [
            'type' => 'correction',
            'status' => 'published',
        ]);

        $this->get('/photos/'.$photo->uuid)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Show')
                ->where('photoDetail.comments.can_submit', false)
                ->where('photoDetail.comments.total', 2)
                ->where('photoDetail.comments.data.0.id', $newer->id)
                ->where('photoDetail.comments.data.0.user_name', '测试用户甲')
                ->where('photoDetail.comments.data.0.content', '较新公开评论')
                ->where('photoDetail.comments.data.1.id', $older->id)
                ->missing('photoDetail.comments.data.2')
            );

        $this->assertDatabaseHas('comments', ['id' => $pending->id]);
        $this->assertDatabaseHas('comments', ['id' => $correction->id]);
    }

    public function test_guest_comment_and_correction_submission_redirect_to_login(): void
    {
        $photo = $this->publicPhoto('登录后评论图片');

        $this->post('/photos/'.$photo->uuid.'/comments', [
            'content' => '游客评论',
        ])->assertRedirect(route('login'));

        $this->post('/photos/'.$photo->uuid.'/corrections', [
            'content' => '游客纠错',
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_submit_pending_discussion_comment(): void
    {
        $photo = $this->publicPhoto('提交普通评论图片');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/photos/'.$photo->uuid.'/comments', [
                'content' => '这张图应该是赛后庆祝。',
            ])
            ->assertCreated()
            ->assertJson([
                'submitted' => true,
                'status' => 'pending',
                'message' => '评论已提交，等待审核。',
            ]);

        $this->assertDatabaseHas('comments', [
            'photo_id' => $photo->id,
            'user_id' => $user->id,
            'type' => 'discussion',
            'content' => '这张图应该是赛后庆祝。',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get('/photos/'.$photo->uuid)
            ->assertInertia(fn (Assert $page) => $page
                ->where('photoDetail.comments.can_submit', true)
                ->where('photoDetail.comments.total', 0)
                ->has('photoDetail.comments.data', 0)
            );
    }

    public function test_authenticated_user_can_submit_pending_correction(): void
    {
        $photo = $this->publicPhoto('提交纠错图片');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/photos/'.$photo->uuid.'/corrections', [
                'correction_field' => 'event_date',
                'suggested_value' => '2022-12-18',
                'evidence_url' => 'https://example.com/source',
                'content' => '这张图应该来自世界杯决赛当天。',
            ])
            ->assertCreated()
            ->assertJson([
                'submitted' => true,
                'status' => 'pending',
                'message' => '补充 / 纠错已提交，等待审核。',
            ]);

        $this->assertDatabaseHas('comments', [
            'photo_id' => $photo->id,
            'user_id' => $user->id,
            'type' => 'correction',
            'content' => '这张图应该来自世界杯决赛当天。',
            'status' => 'pending',
            'correction_field' => 'event_date',
            'suggested_value' => '2022-12-18',
            'evidence_url' => 'https://example.com/source',
        ]);
    }

    public function test_non_public_photos_cannot_receive_comments_or_corrections(): void
    {
        $draft = $this->publicPhoto('草稿评论图片', ['status' => 'draft']);
        $restricted = $this->publicPhoto('受限评论图片', ['copyright_status' => 'restricted']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/photos/'.$draft->uuid.'/comments', ['content' => '不能评论'])
            ->assertNotFound();
        $this->actingAs($user)
            ->postJson('/photos/'.$draft->uuid.'/corrections', ['content' => '不能纠错'])
            ->assertNotFound();
        $this->actingAs($user)
            ->postJson('/photos/'.$restricted->uuid.'/comments', ['content' => '不能评论'])
            ->assertNotFound();
        $this->actingAs($user)
            ->postJson('/photos/'.$restricted->uuid.'/corrections', ['content' => '不能纠错'])
            ->assertNotFound();

        $this->assertDatabaseCount('comments', 0);
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
    private function comment(Photo $photo, User $user, string $content, array $attributes = []): Comment
    {
        return Comment::query()->create(array_merge([
            'photo_id' => $photo->id,
            'user_id' => $user->id,
            'type' => 'discussion',
            'content' => $content,
            'status' => 'published',
        ], $attributes));
    }
}
