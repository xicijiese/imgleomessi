<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Photo;
use App\Models\SupporterProfile;
use App\Models\User;
use App\Notifications\UserCenterNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommentThreadsTest extends TestCase
{
    use RefreshDatabase;

    private function photo()
    {
        return Photo::create(['title' => '评论测试', 'status' => 'published', 'published_at' => now()]);
    }

    private function comment($p, $u, $extra = [])
    {
        return Comment::create(array_merge(['photo_id' => $p->id, 'user_id' => $u->id, 'content' => '公开评论', 'status' => 'published'], $extra));
    }

    public function test_reply_to_reply_is_flattened_and_published(): void
    {
        $u = User::factory()->create();
        $p = $this->photo();
        $root = $this->comment($p, $u);
        $child = $this->comment($p, $u, ['parent_id' => $root->id]);
        $r = $this->actingAs($u)->postJson('/photos/'.$p->uuid.'/comments', ['content' => '回复第二层', 'reply_to' => $child->id])->assertCreated();
        $this->assertDatabaseHas('comments', ['id' => $r->json('id'), 'parent_id' => $root->id, 'status' => 'published']);
        $this->getJson('/photos/'.$p->uuid.'/discussion')->assertJsonCount(2, 'data.0.replies');
    }

    public function test_cross_photo_hidden_and_correction_targets_are_rejected(): void
    {
        $u = User::factory()->create();
        $p = $this->photo();
        $other = $this->photo();
        foreach ([['photo_id' => $other->id], ['status' => 'hidden'], ['type' => 'correction']] as $extra) {
            $c = $this->comment($p, $u, $extra);
            $this->actingAs($u)->postJson('/photos/'.$p->uuid.'/comments', ['content' => '不能回复', 'reply_to' => $c->id])->assertNotFound();
        }
    }

    public function test_comment_likes_are_idempotent_and_require_login(): void
    {
        $u = User::factory()->create();
        $p = $this->photo();
        $c = $this->comment($p, $u);
        $this->postJson('/comments/'.$c->id.'/like')->assertUnauthorized();
        $this->actingAs($u)->postJson('/comments/'.$c->id.'/like')->assertJson(['liked' => true, 'like_count' => 1]);
        $this->postJson('/comments/'.$c->id.'/like')->assertJson(['like_count' => 1]);
        $this->getJson('/photos/'.$p->uuid.'/discussion')->assertJsonPath('data.0.liked', true);
        $this->deleteJson('/comments/'.$c->id.'/like')->assertJson(['liked' => false, 'like_count' => 0]);
        $c->update(['status' => 'hidden']);
        $this->postJson('/comments/'.$c->id.'/like')->assertNotFound();
    }

    public function test_preview_limits_and_reply_pagination(): void
    {
        $u = User::factory()->create();
        $p = $this->photo();
        $root = $this->comment($p, $u);
        for ($i = 0; $i < 12; $i++) {
            $this->comment($p, $u, ['parent_id' => $root->id]);
        }$this->getJson('/photos/'.$p->uuid.'/discussion')->assertJsonCount(3, 'data.0.replies')->assertJsonPath('data.0.reply_count', 12)->assertJsonPath('data.0.author.name', $u->name);
        $this->getJson('/photos/'.$p->uuid.'/discussion?parent='.$root->id)->assertJsonCount(10, 'data')->assertJsonPath('has_more', true);
        $root->update(['status' => 'hidden']);
        $this->getJson('/photos/'.$p->uuid.'/discussion?parent='.$root->id)->assertNotFound();
    }

    public function test_reply_notification_only_after_approval_and_identity_respects_visibility(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['supporter_until' => now()->addDay()]);
        $sender = User::factory()->create();
        $photo = $this->photo();
        SupporterProfile::create(['user_id' => $owner->id, 'show_publicly' => false]);
        $root = $this->comment($photo, $owner);
        $reply = $this->comment($photo, $sender, ['parent_id' => $root->id, 'status' => 'pending', 'meta' => ['reply_to_id' => $root->id]]);
        $this->getJson('/photos/'.$photo->uuid.'/discussion')->assertJsonPath('data.0.author.identities', []);
        Notification::assertNothingSent();
        $reply->review('published');
        Notification::assertSentTo($owner, UserCenterNotification::class);
        $owner->supporterProfile->update(['show_publicly' => true]);
        $this->getJson('/photos/'.$photo->uuid.'/discussion')->assertJsonPath('data.0.author.identities.0', '网站守护者');
        $root->update(['status' => 'hidden']);
        $this->actingAs($sender)->postJson('/comments/'.$reply->id.'/like')->assertNotFound();
    }
}
