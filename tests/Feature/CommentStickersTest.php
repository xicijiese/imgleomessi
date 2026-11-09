<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use App\Services\CommentStickers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentStickersTest extends TestCase
{
    use RefreshDatabase;

    public function test_sticker_and_single_emoji_are_accepted_and_stay_pending(): void
    {
        $p = Photo::create(['title' => '表情测试', 'status' => 'published', 'published_at' => now()]);
        $this->actingAs(User::factory()->create());
        $id = array_key_first(CommentStickers::catalog());
        foreach (['[dy:'.$id.']', '😀', '⚽', '文字[dy:'.$id.']'] as $content) {
            $this->postJson('/photos/'.$p->uuid.'/comments', ['content' => $content])->assertCreated()->assertJsonPath('status', 'pending');
            $this->assertDatabaseHas('comments', ['photo_id' => $p->id, 'content' => $content, 'status' => 'pending']);
        }
        $this->postJson('/photos/'.$p->uuid.'/comments', ['content' => 'a'])->assertUnprocessable();
        $this->postJson('/photos/'.$p->uuid.'/comments', ['content' => '[dy:unknown]'])->assertUnprocessable();
        $this->postJson('/photos/'.$p->uuid.'/comments', ['content' => str_repeat('[dy:'.$id.']', 21)])->assertUnprocessable();
    }

    public function test_catalog_files_are_local_and_moderation_uses_sticker_names(): void
    {
        $items = CommentStickers::catalog();
        $this->assertNotEmpty($items);
        $categories = json_decode(file_get_contents(resource_path('data/douyin-sticker-categories.json')), true, 512, JSON_THROW_ON_ERROR);
        $categoryIds = array_column($categories, 'id');
        $this->assertCount(count($categoryIds), array_unique($categoryIds));
        foreach ($categoryIds as $categoryId) {
            $this->assertNotEmpty(array_filter($items, fn ($item) => $item['category'] === $categoryId));
        }
        foreach ($items as $id => $item) {
            $this->assertContains($item['category'], $categoryIds);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $id);
            $this->assertStringStartsWith('/stickers/douyin/', $item['url']);
            $this->assertFileExists(public_path(ltrim($item['url'], '/')));
        }
        $first = reset($items);
        $this->assertSame('文字'.$first['name'], CommentStickers::scanText('文字[dy:'.$first['id'].']'));
    }
}
