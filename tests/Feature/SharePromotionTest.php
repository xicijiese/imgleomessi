<?php

namespace Tests\Feature;

use App\Filament\Pages\SharePromotions;
use App\Filament\Pages\SystemSettings;
use App\Jobs\GenerateSharePoster;
use App\Models\Album;
use App\Models\Badge;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\ShareLink;
use App\Models\ShareReferral;
use App\Models\ShareVisit;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeService;
use App\Services\HomepageSettings;
use App\Services\SharePromotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class SharePromotionTest extends TestCase
{
    use RefreshDatabase;

    private function link(?User $user = null, string $channel = 'copy_link'): ShareLink
    {
        return app(SharePromotion::class)->link($user, 'home', 'home', $channel);
    }

    private function start(ShareLink $link): array
    {
        $this->get('/s/'.$link->code)->assertRedirect('/?via='.$link->code);

        return $this->postJson('/sharing/visits', ['code' => $link->code, 'path' => '/'])->assertOk()->json();
    }

    private function finish(array $ticket): array
    {
        for ($i = 0; $i < 5; $i++) {
            $this->travel(2)->seconds();
            $ticket = [...$ticket, ...$this->postJson('/sharing/visits/'.$ticket['id'].'/tick', ['receipt' => $ticket['receipt'], 'visible' => true])->assertOk()->json()];
        }

        return $ticket;
    }

    public function test_links_reuse_validate_targets_and_do_not_expose_owner(): void
    {
        $owner = User::factory()->create();
        $result = $this->actingAs($owner)->postJson('/sharing/links', ['type' => 'home', 'key' => 'home', 'channel' => 'copy_link'])->assertOk();
        $this->assertSame($this->link($owner)->code, $result->json('code'));
        $this->assertStringNotContainsString($owner->email, $result->getContent());
        $this->postJson('/sharing/links', ['type' => 'url', 'key' => 'https://example.com', 'channel' => 'copy_link'])->assertUnprocessable();
        $this->postJson('/sharing/links', ['type' => 'photo', 'key' => 'missing', 'channel' => 'copy_link'])->assertNotFound();
        $this->postJson('/sharing/links', ['type' => 'home', 'key' => 'home', 'channel' => 'invalid'])->assertUnprocessable();
    }

    public function test_private_photos_and_empty_albums_are_not_shareable(): void
    {
        $photo = Photo::create(['title' => '私有', 'original_key' => 'a.jpg']);
        $this->postJson('/sharing/links', ['type' => 'photo', 'key' => $photo->uuid, 'channel' => 'copy_link'])->assertNotFound();
        $album = Album::create(['title' => '空相册', 'slug' => 'empty', 'status' => 'published']);
        $this->postJson('/sharing/links', ['type' => 'album', 'key' => $album->slug, 'channel' => 'copy_link'])->assertNotFound();
        $photo->update(['status' => 'published', 'copyright_status' => 'credited']);
        $link = app(SharePromotion::class)->link(null, 'photo', $photo->uuid, 'copy_link');
        $this->get('/s/'.$link->code)->assertRedirect('/photos/'.$photo->uuid.'?via='.$link->code);
        $photo->update(['copyright_status' => 'remove_requested']);
        $this->get('/s/'.$link->code)->assertNotFound();
    }

    public function test_ten_visible_seconds_required_and_replay_is_rejected(): void
    {
        $ticket = $this->start($this->link(User::factory()->create()));
        $old = $ticket['receipt'];
        $result = $this->postJson('/sharing/visits/'.$ticket['id'].'/tick', ['receipt' => $old, 'visible' => true])->assertOk()->json();
        $this->assertSame(0, $result['seconds']);
        $this->postJson('/sharing/visits/'.$ticket['id'].'/tick', ['receipt' => $old, 'visible' => true])->assertConflict();
        $ticket['receipt'] = $result['receipt'];
        $this->travel(20)->seconds();
        $result = $this->postJson('/sharing/visits/'.$ticket['id'].'/tick', ['receipt' => $ticket['receipt'], 'visible' => true])->assertOk()->json();
        $this->assertSame(0, $result['seconds']);
        $ticket['receipt'] = $result['receipt'];
        $result = $this->finish($ticket);
        $this->assertTrue($result['done']);
        $this->assertSame('valid', $result['status']);
        $this->assertDatabaseCount('share_referrals', 1);
    }

    public function test_hidden_time_self_and_anonymous_share_do_not_award(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->actingAs($owner)->start($this->link($owner));
        $this->travel(2)->seconds();
        $result = $this->postJson('/sharing/visits/'.$ticket['id'].'/tick', ['receipt' => $ticket['receipt'], 'visible' => false])->assertOk()->json();
        $this->assertSame(0, $result['seconds']);
        $ticket['receipt'] = $result['receipt'];
        $this->assertSame('excluded', $this->finish($ticket)['status']);
        $this->assertDatabaseCount('share_referrals', 0);
        $this->assertSame('excluded', $this->finish($this->start($this->link()))['status']);
    }

    public function test_same_browser_and_account_only_have_first_effective_attribution(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $visitor = User::factory()->create();
        $this->actingAs($visitor);
        $this->assertSame('valid', $this->finish($this->start($this->link($a)))['status']);
        $this->assertSame('duplicate', $this->finish($this->start($this->link($b)))['status']);
        $this->assertSame(1, ShareReferral::where('status', 'valid')->count());
        $this->assertSame($a->id, ShareReferral::first()->user_id);
    }

    public function test_anonymous_cookie_login_merge_keeps_first_and_preserves_audit(): void
    {
        $service = app(SharePromotion::class);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $visitor = User::factory()->create();
        $first = ShareReferral::create(['share_link_id' => $this->link($owner)->id, 'user_id' => $owner->id]);
        $second = ShareReferral::create(['share_link_id' => $this->link($other)->id, 'user_id' => $other->id]);
        $hash = $service->hash('visitor:browser');
        DB::table('share_identities')->insert([['identity' => $hash, 'share_referral_id' => $first->id, 'created_at' => now(), 'updated_at' => now()], ['identity' => $service->hash('user:'.$visitor->id), 'share_referral_id' => $second->id, 'created_at' => now(), 'updated_at' => now()]]);
        $this->actingAs($visitor)->withSession(['share_visitor_hash' => $hash])->get('/me/shares')->assertOk();
        $this->assertSame('merged', $second->fresh()->status);
        $this->assertSame(1, ShareReferral::where('status', 'valid')->count());
        $this->assertDatabaseHas('share_events', ['event' => 'merged', 'share_referral_id' => $second->id]);
    }

    public function test_session_token_and_landing_path_are_required(): void
    {
        $link = $this->link(User::factory()->create());
        $this->postJson('/sharing/visits', ['code' => $link->code, 'path' => '/'])->assertForbidden();
        $this->get('/s/'.$link->code);
        $this->postJson('/sharing/visits', ['code' => $link->code, 'path' => '/admin'])->assertForbidden();
        $ticket = $this->start($link);
        ShareVisit::whereKey($ticket['id'])->update(['session_hash' => 'other']);
        $this->postJson('/sharing/visits/'.$ticket['id'].'/tick', ['receipt' => $ticket['receipt'], 'visible' => true])->assertForbidden();
    }

    public function test_operations_are_separate_from_referrals_and_non_owner_cannot_write(): void
    {
        $owner = User::factory()->create();
        $link = $this->link($owner);
        $this->actingAs($owner)->postJson('/sharing/'.$link->code.'/operation')->assertOk();
        $this->assertSame(1, $link->fresh()->operations);
        $this->assertDatabaseCount('share_referrals', 0);
        $this->actingAs(User::factory()->create())->postJson('/sharing/'.$link->code.'/operation')->assertForbidden();
    }

    public function test_disabled_links_and_restricted_owners_stop_existing_receipts(): void
    {
        $owner = User::factory()->create();
        $link = $this->link($owner);
        $ticket = $this->start($link);
        $link->update(['is_active' => false]);
        $this->postJson('/sharing/visits/'.$ticket['id'].'/tick', ['receipt' => $ticket['receipt'], 'visible' => true])->assertGone();
        $link->update(['is_active' => true]);
        $owner->update(['status' => 'disabled']);
        $this->get('/s/'.$link->code)->assertGone();
    }

    public function test_fifty_referrals_award_once_without_auto_equip_or_revoke(): void
    {
        $owner = User::factory()->create();
        $link = $this->link($owner);
        $badge = Badge::create(['name' => '传播者', 'slug' => 'promotion', 'rule_type' => 'referrals_count', 'rule_threshold' => 50, 'is_active' => true]);
        for ($i = 0; $i < 49; $i++) {
            ShareReferral::create(['share_link_id' => $link->id, 'user_id' => $owner->id]);
        }
        $this->finish($this->start($link));
        $grant = UserBadge::where('user_id', $owner->id)->where('badge_id', $badge->id)->firstOrFail();
        $this->assertFalse($grant->equipped);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        app(SharePromotion::class)->moderate($admin, 'referral', ShareReferral::first()->id, false, '测试纠正');
        $this->assertSame('earned', $grant->fresh()->status);
        app(BadgeService::class)->revoke($grant, $admin, '作弊撤销');
        ShareReferral::create(['share_link_id' => $link->id, 'user_id' => $owner->id]);
        app(BadgeService::class)->evaluate($owner);
        $this->assertSame('revoked', $grant->fresh()->status);
    }

    public function test_poster_queue_private_access_generation_and_disabled_content(): void
    {
        Queue::fake();
        Storage::fake('local');
        $owner = User::factory()->create();
        $link = $this->link($owner, 'poster');
        $this->actingAs($owner)->postJson('/sharing/'.$link->code.'/poster')->assertOk()->assertJsonPath('status', 'pending');
        $this->postJson('/sharing/'.$link->code.'/poster')->assertOk();
        Queue::assertPushed(GenerateSharePoster::class, 1);
        $job = new GenerateSharePoster($link->id, $link->fresh()->poster_fingerprint);
        app()->call([$job, 'handle']);
        $this->get('/sharing/'.$link->code.'/poster/image')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith("\x89PNG", Storage::disk('local')->get($link->fresh()->poster_path));
        $this->actingAs(User::factory()->create())->get('/sharing/'.$link->code.'/poster/image')->assertForbidden();
        $link->update(['is_active' => false]);
        $this->actingAs($owner)->get('/sharing/'.$link->code.'/poster/image')->assertGone();
    }

    public function test_cleanup_removes_only_expired_details_and_keeps_dedup_and_totals(): void
    {
        $link = $this->link(User::factory()->create());
        $this->finish($this->start($link));
        ShareVisit::query()->update(['created_at' => now()->subDays(91)]);
        $this->artisan('sharing:cleanup')->assertSuccessful();
        $this->assertDatabaseCount('share_visits', 0);
        $this->assertDatabaseCount('share_referrals', 1);
        $this->assertGreaterThan(0, DB::table('share_identities')->count());
        $this->assertSame(1, $link->fresh()->visits);
    }

    public function test_user_center_only_exposes_own_summary_and_admin_tabs_are_authorized(): void
    {
        $owner = User::factory()->create();
        $link = $this->link($owner);
        $this->actingAs($owner)->get('/me/shares')->assertInertia(fn (Assert $page) => $page->component('Me/Shares')->missing('me.links')->where('me.effective_count', 0));
        $this->get('/admin/share-promotions')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'editor']))->get('/admin/share-promotions')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']))->get('/admin/share-promotions')->assertOk();
        Livewire::test(SharePromotions::class)->call('selectTab', 'links')->assertSee($link->code)->call('selectTab', 'visits')->assertSuccessful()->call('selectTab', 'events')->assertSuccessful();
    }

    public function test_crawlers_cannot_claim_visits(): void
    {
        $link = $this->link(User::factory()->create());
        $this->get('/s/'.$link->code);
        $this->withHeader('User-Agent', 'ExampleBot/1.0')->postJson('/sharing/visits', ['code' => $link->code, 'path' => '/'])->assertForbidden();
    }

    public function test_link_creation_marks_browser_for_self_exclusion_after_logout(): void
    {
        $owner = User::factory()->create();
        $result = $this->actingAs($owner)->postJson('/sharing/links', ['type' => 'home', 'key' => 'home', 'channel' => 'copy_link'])->assertOk();
        $hash = session('share_visitor_hash');
        $this->assertDatabaseHas('share_known_users', ['identity' => $hash, 'user_id' => $owner->id]);
        auth()->forgetGuards();
        $this->app['auth']->guard()->logout();
        $ticket = $this->start(ShareLink::where('code', $result->json('code'))->firstOrFail());
        $this->assertSame('excluded', $this->finish($ticket)['status']);
        $this->assertDatabaseCount('share_referrals', 0);
    }

    public function test_visible_ticks_cannot_be_claimed_after_receipt_expires(): void
    {
        $ticket = $this->start($this->link(User::factory()->create()));
        $this->travel(31)->minutes();
        $this->postJson('/sharing/visits/'.$ticket['id'].'/tick', ['receipt' => $ticket['receipt'], 'visible' => true])->assertGone();
        $this->assertDatabaseCount('share_referrals', 0);
    }

    public function test_summary_and_dedup_survive_ninety_day_visit_cleanup(): void
    {
        $link = $this->link(User::factory()->create());
        $this->finish($this->start($link));
        ShareVisit::query()->update(['created_at' => now()->subDays(91)]);
        $this->artisan('sharing:cleanup')->assertSuccessful();
        $this->assertSame('duplicate', $this->finish($this->start($link))['status']);
        $this->assertSame(1, ShareReferral::where('status', 'valid')->count());
        $this->assertSame(1, $link->fresh()->unique_visitors);
    }

    public function test_admin_correction_is_audited_and_cannot_reassign_merged_identity(): void
    {
        $owner = User::factory()->create();
        $link = $this->link($owner);
        $this->finish($this->start($link));
        $ref = ShareReferral::firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);
        Livewire::test(SharePromotions::class)->call('selectTab', 'visits')->callTableAction('referrals', $ref, ['reason' => '访问异常核实'])->assertHasNoTableActionErrors();
        $this->assertSame('revoked', $ref->fresh()->status);
        $this->assertDatabaseHas('share_events', ['actor_id' => $admin->id, 'share_referral_id' => $ref->id, 'reason' => '访问异常核实']);
        Livewire::test(SharePromotions::class)->call('selectTab', 'links')->callTableAction('toggle', $link, ['reason' => '链接异常'])->assertHasNoTableActionErrors();
        $this->assertFalse($link->fresh()->is_active);
    }

    public function test_rejected_topic_and_shared_settings_are_not_open_redirects(): void
    {
        $this->postJson('/sharing/links', ['type' => 'topic', 'key' => 'nonexistent', 'channel' => 'copy_link'])->assertNotFound();
        $settings = app(HomepageSettings::class)->formState();
        $settings['topic_module']['items'] = [['title' => '公开专题', 'url' => '/topics/promotion-test', 'enabled' => true, 'photo_ids' => [], 'album_ids' => []]];
        app(HomepageSettings::class)->save($settings);
        $this->postJson('/sharing/links', ['type' => 'topic', 'key' => 'promotion-test', 'channel' => 'copy_link'])->assertOk();
        $this->postJson('/sharing/links', ['type' => 'home', 'key' => 'https://example.com', 'channel' => 'copy_link'])->assertNotFound();
    }

    public function test_admin_share_defaults_save_without_changing_homepage_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);
        $before = app(HomepageSettings::class)->formState()['site']['name'];
        Livewire::test(SystemSettings::class)
            ->set('data.sharing.title', '推广宣传标题')
            ->set('data.sharing.description', '分享网站的宣传摘要')
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('推广宣传标题', Setting::value('sharing', 'defaults')['title']);
        $this->assertSame($before, app(HomepageSettings::class)->formState()['site']['name']);
        $this->assertSame('推广宣传标题', app(SharePromotion::class)->target('home', 'home')['title']);
    }
}
