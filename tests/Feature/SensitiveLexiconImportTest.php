<?php

namespace Tests\Feature;

use App\Filament\Resources\SensitiveWords\Pages\ManageSensitiveWords;
use App\Models\Photo;
use App\Models\SensitiveWord;
use App\Models\User;
use App\Services\LocalSensitiveLexicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SensitiveLexiconImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_explicit_preserves_existing_rules_and_does_not_repeat(): void
    {
        $word = app(LocalSensitiveLexicon::class)->words()[0];
        $rule = SensitiveWord::create(['word' => $word, 'is_enabled' => false, 'severity' => 'low', 'internal_note' => '管理员自定义']);
        $this->artisan('moderation:import-sensitive-words')->assertSuccessful();
        $this->assertDatabaseCount('sensitive_words', 1);
        $this->assertFalse(app(LocalSensitiveLexicon::class)->imported());
        $this->artisan('moderation:import-sensitive-words --apply')->assertSuccessful();
        $count = SensitiveWord::count();
        $this->assertGreaterThan(16000, $count);
        $this->assertDatabaseHas('sensitive_words', ['id' => $rule->id, 'is_enabled' => false, 'severity' => 'low', 'internal_note' => '管理员自定义']);
        $this->assertTrue(app(LocalSensitiveLexicon::class)->imported());
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        Livewire::test(ManageSensitiveWords::class)
            ->searchTable('出售雷管')
            ->assertCanSeeTableRecords(SensitiveWord::where('word', '出售雷管')->get());
        $this->artisan('moderation:import-sensitive-words --apply')->assertSuccessful();
        $this->assertDatabaseCount('sensitive_words', $count);
    }

    public function test_admin_changes_and_deletion_control_actual_comment_filtering(): void
    {
        $lexicon = app(LocalSensitiveLexicon::class);
        $lexicon->import(true);
        SensitiveWord::query()->update(['is_enabled' => false]);
        $word = $lexicon->words()[0];
        $rule = SensitiveWord::where('word', $word)->firstOrFail();
        $rule->update(['is_enabled' => true]);
        $photo = Photo::create(['title' => '规则管理测试', 'status' => 'published', 'published_at' => now()]);
        $this->actingAs(User::factory()->create());
        $url = '/photos/'.$photo->uuid.'/comments';
        $this->postJson($url, ['content' => $word])->assertUnprocessable();
        $rule->update(['is_enabled' => false]);
        $this->postJson($url, ['content' => $word])->assertCreated();
        $rule->update(['word' => '后台新增测试禁词', 'is_enabled' => true]);
        $this->postJson($url, ['content' => $word])->assertCreated();
        $this->postJson($url, ['content' => '后台新增测试禁词'])->assertUnprocessable();
        $rule->delete();
        $this->postJson($url, ['content' => '后台新增测试禁词'])->assertCreated();
        $lexicon->import(true);
        $this->assertDatabaseMissing('sensitive_words', ['word' => $word]);
    }
}
