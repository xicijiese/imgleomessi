<?php

namespace Tests\Feature;

use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\SensitiveWords\SensitiveWordResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\Report;
use App\Models\SensitiveWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderation_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['status', 'banned_until', 'ban_reason', 'moderation_note']));
        $this->assertTrue(Schema::hasColumns('comments', ['reviewed_by', 'reviewed_at', 'moderation_note', 'risk_level', 'sensitive_word_hits']));
        $this->assertTrue(Schema::hasColumns('reports', ['user_id', 'target_type', 'target_id', 'reason', 'details', 'status', 'handled_by', 'handled_at', 'internal_note', 'risk_level', 'sensitive_word_hits']));
        $this->assertTrue(Schema::hasColumns('sensitive_words', ['word', 'severity', 'is_enabled', 'internal_note']));
    }

    public function test_sensitive_words_mark_comments_and_corrections_without_auto_rejecting(): void
    {
        SensitiveWord::query()->create([
            'word' => '敏感测试词',
            'severity' => 'high',
            'is_enabled' => true,
        ]);
        $photo = $this->publicPhoto('敏感词测试图片');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/photos/'.$photo->uuid.'/comments', [
                'content' => '这里包含敏感测试词，但仍应进入人工审核。',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->actingAs($user)
            ->postJson('/photos/'.$photo->uuid.'/corrections', [
                'correction_field' => 'source',
                'suggested_value' => '敏感测试词来源线索',
                'content' => '补充一个来源线索。',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('comments', [
            'photo_id' => $photo->id,
            'type' => 'discussion',
            'status' => 'pending',
            'risk_level' => 'high',
        ]);
        $this->assertDatabaseHas('comments', [
            'photo_id' => $photo->id,
            'type' => 'discussion',
            'status' => 'pending',
            'risk_level' => 'high',
        ]);
    }

    public function test_authenticated_user_can_report_published_discussion_comment(): void
    {
        SensitiveWord::query()->create([
            'word' => '广告词',
            'severity' => 'medium',
            'is_enabled' => true,
        ]);
        $photo = $this->publicPhoto('可举报图片');
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $comment = $this->comment($photo, $author, '这是一条公开评论。');

        $this->actingAs($reporter)
            ->postJson('/comments/'.$comment->id.'/reports', [
                'reason' => 'spam',
                'details' => '包含广告词。',
            ])
            ->assertCreated()
            ->assertJson([
                'submitted' => true,
                'status' => 'pending',
                'message' => '举报已提交，等待处理。',
            ]);

        $this->assertDatabaseHas('reports', [
            'user_id' => $reporter->id,
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'reason' => 'spam',
            'status' => 'pending',
            'risk_level' => 'medium',
        ]);
    }

    public function test_report_boundaries_reject_guest_banned_and_non_public_targets(): void
    {
        $photo = $this->publicPhoto('举报边界图片');
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $banned = User::factory()->create(['status' => 'banned']);
        $published = $this->comment($photo, $author, '公开评论。');
        $pending = $this->comment($photo, $author, '待审评论。', ['status' => 'pending']);
        $correction = $this->comment($photo, $author, '公开纠错。', ['type' => 'correction']);

        $this->post('/comments/'.$published->id.'/reports', ['reason' => 'spam'])
            ->assertRedirect(route('login'));

        $this->actingAs($banned)
            ->postJson('/comments/'.$published->id.'/reports', ['reason' => 'spam'])
            ->assertForbidden();

        $this->actingAs($reporter)
            ->postJson('/comments/'.$pending->id.'/reports', ['reason' => 'spam'])
            ->assertNotFound();

        $this->actingAs($reporter)
            ->postJson('/comments/'.$correction->id.'/reports', ['reason' => 'spam'])
            ->assertNotFound();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_review_and_report_resolution_keep_audit_state_without_physical_delete(): void
    {
        $photo = $this->publicPhoto('审核流转图片');
        $author = User::factory()->create();
        $admin = User::factory()->create();
        $comment = $this->comment($photo, $author, '需要处理的评论。', ['status' => 'pending']);
        $report = Report::query()->create([
            'user_id' => $author->id,
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'reason' => 'abuse',
        ]);

        $this->assertTrue($comment->review('published', $admin, '审核通过'));
        $comment->refresh();

        $this->assertSame('published', $comment->status);
        $this->assertSame($admin->id, $comment->reviewed_by);
        $this->assertNotNull($comment->reviewed_at);
        $this->assertSame('审核通过', $comment->moderation_note);

        $this->assertTrue($report->resolve($admin, '违规隐藏', true));
        $report->refresh();
        $comment->refresh();

        $this->assertSame('resolved', $report->status);
        $this->assertSame($admin->id, $report->handled_by);
        $this->assertSame('hidden', $comment->status);
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'status' => 'hidden']);
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'resolved']);
    }

    public function test_banned_user_can_browse_but_cannot_write_interactions_or_ugc(): void
    {
        $photo = $this->publicPhoto('封禁用户边界图片');
        $banned = User::factory()->create(['status' => 'banned']);
        $comment = $this->comment($photo, User::factory()->create(), '可举报评论。');

        $this->actingAs($banned)
            ->get('/photos/'.$photo->uuid)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('photoDetail.photo.interactions.can_interact', false)
                ->where('photoDetail.photo.interactions.is_blocked', true)
                ->where('photoDetail.comments.can_submit', false)
                ->where('photoDetail.comments.can_report', false)
                ->where('photoDetail.comments.is_blocked', true)
            );

        $this->actingAs($banned)->postJson('/photos/'.$photo->uuid.'/comments', ['content' => '不能评论'])->assertForbidden();
        $this->actingAs($banned)->postJson('/photos/'.$photo->uuid.'/corrections', ['content' => '不能纠错'])->assertForbidden();
        $this->actingAs($banned)->postJson('/comments/'.$comment->id.'/reports', ['reason' => 'spam'])->assertForbidden();
        $this->actingAs($banned)->postJson('/photos/'.$photo->uuid.'/favorite')->assertForbidden();
        $this->actingAs($banned)->postJson('/photos/'.$photo->uuid.'/like')->assertForbidden();
        $this->actingAs($banned)->postJson('/photos/'.$photo->uuid.'/shares', ['channel' => 'copy_link'])->assertForbidden();

        $this->assertDatabaseCount('comments', 1);
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('photo_favorites', 0);
        $this->assertDatabaseCount('photo_likes', 0);
        $this->assertDatabaseCount('photo_shares', 0);
    }

    public function test_admin_can_visit_moderation_resources(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(CommentResource::getUrl())->assertOk();
        $this->get(ReportResource::getUrl())->assertOk();
        $this->get(SensitiveWordResource::getUrl())->assertOk();
        $this->get(UserResource::getUrl())->assertOk();
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
