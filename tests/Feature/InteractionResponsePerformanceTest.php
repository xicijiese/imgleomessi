<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InteractionResponsePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_interaction_and_share_request_costs(): void
    {
        $user = User::factory()->create();
        $photo = Photo::query()->create(['title' => '性能测试图片', 'status' => 'published', 'published_at' => now()]);
        $this->actingAs($user);
        $rows = [];
        foreach (['like', 'unlike', 'favorite', 'unfavorite', 'share'] as $action) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $start = hrtime(true);
            $response = match ($action) {
                'like' => $this->postJson('/photos/'.$photo->uuid.'/like'),
                'unlike' => $this->deleteJson('/photos/'.$photo->uuid.'/like'),
                'favorite' => $this->postJson('/photos/'.$photo->uuid.'/favorite'),
                'unfavorite' => $this->deleteJson('/photos/'.$photo->uuid.'/favorite'),
                default => $this->postJson('/sharing/links', ['type' => 'photo', 'key' => $photo->uuid, 'channel' => 'copy_link'])
            };
            $ms = (hrtime(true) - $start) / 1e6;
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $response->assertOk();
            $rows[$action] = ['ms' => round($ms, 2), 'queries' => count($queries), 'settings' => count(array_filter($queries, fn ($q) => str_contains($q['query'], '"settings"')))];
        }
        fwrite(STDERR, json_encode($rows).PHP_EOL);
        foreach (['like', 'unlike', 'favorite', 'unfavorite'] as $action) {
            $this->assertSame(0, $rows[$action]['settings']);
        }
        $this->assertLessThanOrEqual(2, $rows['share']['settings']);
        $this->postJson('/sharing/links', ['type' => 'photo', 'key' => $photo->uuid, 'channels' => ['copy_link', 'native_share']])->assertOk()->assertJsonStructure(['links' => ['copy_link' => ['url', 'code', 'title'], 'native_share' => ['url', 'code', 'title']]]);
        $this->postJson('/sharing/links', ['type' => 'photo', 'key' => $photo->uuid, 'channels' => ['invalid']])->assertUnprocessable();
        $this->assertFalse($user->photoFavorites()->where('photo_id', $photo->id)->exists());
    }
}
