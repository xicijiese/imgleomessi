<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Photo;
use App\Models\SensitiveWord;
use App\Models\User;
use App\Notifications\UserCenterNotification;
use App\Services\CommentStickers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutomaticCommentPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_stickers_publish_in_comments_and_replies_without_scanning_their_names(): void
    {
        $photo = Photo::create(['title' => '表情评论测试', 'status' => 'published', 'published_at' => now()]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $content = '[dy:d604119face8e43c][dy:d604119face8e43c]';
        $root = $this->postJson('/photos/'.$photo->uuid.'/comments', compact('content'))
            ->assertCreated()->assertJsonPath('status', 'published')->json('id');
        $this->postJson('/photos/'.$photo->uuid.'/comments', ['content' => $content, 'reply_to' => $root])
            ->assertCreated()->assertJsonPath('status', 'published');
        foreach (array_chunk(array_keys(CommentStickers::catalog()), 20) as $ids) {
            $this->travel(1)->minutes();
            $content = implode('', array_map(fn ($id) => '[dy:'.$id.']', $ids));
            $response = $this->postJson('/photos/'.$photo->uuid.'/comments', ['content' => $content, 'reply_to' => $root])
                ->assertCreated()->assertJsonPath('status', 'published');
            $this->assertDatabaseHas('comments', ['id' => $response->json('id'), 'content' => $content]);
        }
    }

    public function test_stickers_do_not_hide_sensitive_text_or_allow_unknown_sticker_ids(): void
    {
        $photo = Photo::create(['title' => '混排评论测试', 'status' => 'published', 'published_at' => now()]);
        $this->actingAs(User::factory()->create());
        foreach (['出售雷管[dy:d604119face8e43c]', '出售[dy:d604119face8e43c]雷管', '[dy:unknown]'] as $content) {
            $this->postJson('/photos/'.$photo->uuid.'/comments', compact('content'))
                ->assertUnprocessable()->assertJsonValidationErrors('content');
        }
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_clean_comments_publish_and_replies_notify_without_review(): void
    {
        Notification::fake();
        $photo = Photo::create(['title' => '测试图片', 'status' => 'published', 'published_at' => now()]);
        $owner = User::factory()->create();
        $root = $this->actingAs($owner)->postJson('/photos/'.$photo->uuid.'/comments', ['content' => '梅西世界杯夺冠，和队友一起庆祝！'])
            ->assertCreated()->assertJsonPath('status', 'published')->json('id');
        $this->actingAs(User::factory()->create())->postJson('/photos/'.$photo->uuid.'/comments', ['content' => '这张训练照片值得收藏', 'reply_to' => $root])
            ->assertCreated()->assertJsonPath('status', 'published');
        Notification::assertSentTo($owner, UserCenterNotification::class, fn ($n) => $n->toArray($owner)['category'] === 'comment_reply');
        $this->getJson('/photos/'.$photo->uuid.'/discussion')->assertJsonPath('total', 1)->assertJsonCount(1, 'data.0.replies');
        $this->assertDatabaseHas('comments', ['id' => $root, 'reviewed_by' => null, 'reviewed_at' => null]);
    }

    public function test_local_words_and_obfuscated_custom_rules_block_comments_without_saving(): void
    {
        $photo = Photo::create(['title' => '测试图片', 'status' => 'published', 'published_at' => now()]);
        $this->actingAs(User::factory()->create());
        SensitiveWord::create(['word' => 'BADWORD', 'severity' => 'low']);
        foreach (['出售雷管', 'hello badword friend', '这里有ＢＡＤＷＯＲＤ', "这里有B A\u{200B}D W O R D"] as $content) {
            $this->postJson('/photos/'.$photo->uuid.'/comments', compact('content'))
                ->assertUnprocessable()->assertJsonValidationErrors('content');
        }
        $this->assertDatabaseCount('comments', 0);
        SensitiveWord::where('word', 'BADWORD')->update(['is_enabled' => false]);
        $this->postJson('/photos/'.$photo->uuid.'/comments', ['content' => 'BADWORD'])
            ->assertCreated()->assertJsonPath('status', 'published');
    }

    public function test_blocked_reply_sends_no_notification_and_old_pending_is_not_published(): void
    {
        Notification::fake();
        $photo = Photo::create(['title' => '测试图片', 'status' => 'published', 'published_at' => now()]);
        $owner = User::factory()->create();
        $root = Comment::create(['photo_id' => $photo->id, 'user_id' => $owner->id, 'content' => '公开评论', 'status' => 'published']);
        $old = Comment::create(['photo_id' => $photo->id, 'user_id' => $owner->id, 'content' => '历史待审评论']);
        $this->actingAs(User::factory()->create())->postJson('/photos/'.$photo->uuid.'/comments', ['content' => '出售雷管', 'reply_to' => $root->id])->assertUnprocessable();
        Notification::assertNothingSent();
        $this->assertDatabaseHas('comments', ['id' => $old->id, 'status' => 'pending']);
        $this->assertDatabaseCount('comments', 2);
    }
}
