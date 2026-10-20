<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Setting;
use App\Models\SponsorshipOrder;
use App\Models\SponsorshipPlan;
use App\Models\SupporterProfile;
use App\Models\User;
use App\Services\PaymentSettings;
use App\Services\RealSponsorshipService;
use App\Services\SponsorshipMaintenance;
use App\Services\SponsorshipService;
use App\Services\XunhuGateway;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RealSponsorshipTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        app(PaymentSettings::class)->save(['enabled' => true, 'appid' => 'channel-app', 'secret' => 'test-channel-secret', 'gateway' => 'https://api.xunhuweb.com/payment/do.html', 'query_gateway' => 'https://channel.example/query', 'refund_gateway' => 'https://channel.example/refund'], null);
        Http::preventStrayRequests();
        Http::fake(['https://api.xunhuweb.com/payment/do.html' => Http::response($this->signed(['errcode' => 0, 'url' => 'https://channel.example/pay', 'url_qrcode' => 'https://channel.example/qr.png']))]);
    }

    private function signed(array $data, string $secret = 'test-channel-secret'): array
    {
        return [...$data, 'hash' => XunhuGateway::sign($data, $secret)];
    }

    private function plan(array $extra = []): SponsorshipPlan
    {
        return SponsorshipPlan::create([...['name' => '月度支持', 'slug' => uniqid('support'), 'amount_cents' => 900, 'duration_days' => 30, 'badge_level' => 'silver', 'is_active' => true], ...$extra]);
    }

    private function create(?User $user = null, array $plan = []): SponsorshipOrder
    {
        return app(RealSponsorshipService::class)->create($user ?? User::factory()->create(['status' => 'active']), $this->plan($plan));
    }

    private function notify(SponsorshipOrder $order, array $extra = [], string $secret = 'test-channel-secret')
    {
        return $this->post('/payments/xunhu/notify', $this->signed([...[
            'appid' => 'channel-app', 'trade_order_id' => $order->order_no, 'total_fee' => '9.00',
            'transaction_id' => 'TX-'.$order->id, 'status' => 'OD', 'time' => time(), 'nonce_str' => 'callback-test',
        ], ...$extra], $secret));
    }

    public function test_real_payment_is_closed_by_default_and_only_normal_users_can_order(): void
    {
        Http::preventStrayRequests();
        $plan = $this->plan();
        $this->post('/support/orders', ['plan_id' => $plan->id])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['status' => 'active']))->post('/support/orders', ['plan_id' => $plan->id])->assertSessionHasErrors('plan_id');
        $this->assertDatabaseCount('sponsorship_orders', 0);
        $this->configure();
        foreach (['disabled', 'banned'] as $status) {
            $user = User::factory()->create(['status' => $status]);
            try {
                app(RealSponsorshipService::class)->create($user, $plan);
                $this->fail('异常账号不能付款');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        Http::assertNothingSent();
    }

    public function test_order_snapshots_secret_and_plan_and_deduplicates_repeated_clicks(): void
    {
        $this->configure();
        $user = User::factory()->create(['status' => 'active']);
        $plan = $this->plan();
        $this->actingAs($user)->post('/support/orders', ['plan_id' => $plan->id, 'amount_cents' => 1])->assertRedirect();
        $order = SponsorshipOrder::firstOrFail();
        $this->actingAs($user)->post('/support/orders', ['plan_id' => $plan->id])->assertRedirect();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.xunhuweb.com/payment/do.html' && $request['total_fee'] === '9.00' && XunhuGateway::verify($request->data(), 'test-channel-secret'));
        $plan->update(['name' => '已改名', 'duration_days' => 2, 'amount_cents' => 100]);
        $this->assertSame(900, $order->amount_cents);
        $this->assertSame(30, $order->plan_snapshot['duration_days']);
        $this->assertStringNotContainsString('test-channel-secret', DB::table('sponsorship_orders')->value('gateway_credentials'));
        $this->assertArrayNotHasKey('gateway_credentials', $order->toArray());
        $this->get('/support/result?order='.$order->order_no)->assertInertia(fn (Assert $page) => $page->where('supportResult.order.plan_name', '月度支持'));
    }

    public function test_only_verified_callback_grants_once_and_return_page_does_not_grant(): void
    {
        $this->configure();
        $order = $this->create();
        $this->actingAs($order->user)->get('/support/result?order='.$order->order_no.'&status=paid')->assertOk();
        $this->assertSame('pending', $order->fresh()->status);
        $this->notify($order, [], 'wrong-secret')->assertStatus(400);
        $this->notify($order, ['total_fee' => '0.01'])->assertStatus(400);
        $this->notify($order, ['appid' => 'wrong-app'])->assertStatus(400);
        $this->assertFalse($order->user->fresh()->isSupporter());
        $this->notify($order)->assertOk()->assertSeeText('success');
        $until = $order->user->fresh()->supporter_until->toISOString();
        $this->notify($order)->assertOk();
        $this->assertSame($until, $order->user->fresh()->supporter_until->toISOString());
        $this->assertSame(1, $order->user->notifications()->count());
        $this->assertSame(900, $order->user->supporterProfile()->first()->total_amount_cents);
    }

    public function test_old_callback_survives_key_rotation_and_disabled_new_payments(): void
    {
        $this->configure();
        $order = $this->create();
        app(PaymentSettings::class)->save(['enabled' => false, 'appid' => 'replacement-app', 'secret' => 'new-secret'], null);
        $this->notify($order)->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('', app(PaymentSettings::class)->formState()['secret']);
        $this->assertStringNotContainsString('new-secret', json_encode(Setting::value('payment', 'xunhu')));
    }

    public function test_one_time_payment_only_adds_history_and_expired_identity_is_not_permanent(): void
    {
        $this->configure();
        $order = $this->create(null, ['duration_days' => null]);
        $this->notify($order)->assertOk();
        $this->assertFalse($order->user->fresh()->isSupporter());
        $this->assertNull($order->user->fresh()->supporter_until);
        $this->assertTrue($order->user->supporterProfile()->first()->show_publicly);
        $limited = $this->create();
        $this->notify($limited)->assertOk();
        $this->travel(31)->days();
        $this->assertFalse($limited->user->fresh()->isSupporter());
    }

    public function test_refund_rebuilds_renewal_and_preserves_other_order_and_legacy(): void
    {
        $this->configure();
        $user = User::factory()->create(['status' => 'active', 'supporter_until' => now()->addDays(10)]);
        SupporterProfile::create(['user_id' => $user->id, 'total_amount_cents' => 100, 'show_publicly' => false, 'badge_level' => 'gold']);
        $first = $this->create($user, ['badge_level' => 'silver']);
        $this->notify($first)->assertOk();
        $second = $this->create($user, ['badge_level' => 'supporter']);
        $this->notify($second)->assertOk();
        $this->assertSame(70, (int) round(now()->diffInDays($user->fresh()->supporter_until)));
        $this->assertSame('gold', $user->supporterProfile()->first()->badge_level);
        $this->notify($first, ['status' => 'CD'])->assertOk();
        $this->assertSame(40, (int) round(now()->diffInDays($user->fresh()->supporter_until)));
        $this->assertSame(1000, $user->supporterProfile()->first()->total_amount_cents);
        $this->assertFalse($user->supporterProfile()->first()->show_publicly);
        $this->notify($second, ['status' => 'CD'])->assertOk();
        $this->assertTrue($user->fresh()->isSupporter());
        $this->assertSame(100, $user->supporterProfile()->first()->total_amount_cents);
    }

    public function test_refund_revokes_only_supporter_badge_and_is_idempotent(): void
    {
        $this->configure();
        $badge = Badge::create(['name' => '运营守护者', 'slug' => 'paid-support', 'rule_type' => 'supporter', 'is_active' => true]);
        $other = Badge::create(['name' => '注册', 'slug' => 'registered-real', 'rule_type' => 'registered', 'is_active' => true]);
        $order = $this->create();
        $this->notify($order)->assertOk();
        $this->assertDatabaseHas('user_badges', ['user_id' => $order->user_id, 'badge_id' => $badge->id, 'status' => 'earned', 'equipped' => false]);
        $this->notify($order, ['status' => 'CD'])->assertOk();
        $this->notify($order, ['status' => 'CD'])->assertOk();
        $this->notify($order)->assertOk();
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertFalse($order->user->fresh()->isSupporter());
        $this->assertDatabaseHas('user_badges', ['user_id' => $order->user_id, 'badge_id' => $badge->id, 'status' => 'revoked', 'equipped' => false]);
        $this->assertDatabaseHas('user_badges', ['user_id' => $order->user_id, 'badge_id' => $other->id, 'status' => 'earned']);
        $this->assertSame(3, DB::table('badge_events')->count()); // 两枚授予、一次撤销；另无重复事件。
    }

    public function test_late_payment_is_recorded_and_transaction_cannot_pay_two_orders(): void
    {
        $this->configure();
        $order = $this->create();
        $order->update(['status' => 'closed']);
        $this->notify($order)->assertOk();
        $other = $this->create();
        $this->notify($other, ['transaction_id' => 'TX-'.$order->id])->assertStatus(400);
        $this->assertSame('pending', $other->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_refund_requires_admin_and_reason_and_confirmed_channel_result(): void
    {
        $this->configure();
        $order = $this->create();
        $this->notify($order)->assertOk();
        Http::fake(fn () => Http::response($this->signed(['errcode' => 0, 'trade_order_id' => $order->order_no, 'transaction_id' => 'TX-'.$order->id, 'refund_fee' => '9.00', 'refund_status' => 'CD', 'out_refund_no' => 'RF-001'])));
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $result = app(RealSponsorshipService::class)->refund($order, $admin, '用户联系管理员申请退款');
        $this->assertSame('refunded', $result->status);
        $this->assertSame('RF-001', $result->refund_no);
        $this->assertFalse($order->user->fresh()->isSupporter());
    }

    public function test_unknown_refund_does_not_retry_or_revoke_paid_rights(): void
    {
        $this->configure();
        $order = $this->create();
        $this->notify($order)->assertOk();
        Http::fake(fn () => Http::response('gateway timeout', 504));
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $result = app(RealSponsorshipService::class)->refund($order, $admin, '退款申请');
        $this->assertSame('paid', $result->status);
        $this->assertSame('unknown', $result->refund_state);
        $this->assertTrue($order->user->fresh()->isSupporter());
        $before = DB::table('payment_attempts')->count();
        try {
            app(RealSponsorshipService::class)->refund($result, $admin, '再次退款');
            $this->fail('未知退款不得重复发起');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame($before, DB::table('payment_attempts')->count());
    }

    public function test_nested_query_is_flagged_instead_of_guessing_signature_and_granting(): void
    {
        $this->configure();
        $order = $this->create();
        Http::fake(fn () => Http::response(['errcode' => 0, 'data' => ['status' => 'OD'], 'hash' => 'not-verifiable']));
        app(RealSponsorshipService::class)->query($order);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->payment_issue);
    }

    public function test_query_cancel_is_not_refund_and_owner_is_enforced(): void
    {
        $this->configure();
        $order = $this->create();
        Http::fake(fn () => Http::response($this->signed(['errcode' => 0, 'status' => 'CD', 'trade_order_id' => $order->order_no, 'appid' => 'channel-app'])));
        $this->actingAs(User::factory()->create())->post('/support/orders/'.$order->id.'/check')->assertForbidden();
        $this->actingAs($order->user)->post('/support/orders/'.$order->id.'/check')->assertRedirect();
        $this->assertSame('closed', $order->fresh()->status);
        $this->assertNull($order->fresh()->refunded_at);
    }

    public function test_reminders_deduplicate_and_renewal_changes_expiry_key(): void
    {
        $this->configure();
        $order = $this->create(null, ['duration_days' => 7]);
        $this->notify($order)->assertOk();
        $this->assertSame(1, app(SponsorshipMaintenance::class)->remind());
        $this->assertSame(0, app(SponsorshipMaintenance::class)->remind());
        $this->travel(6)->days();
        $this->assertSame(1, app(SponsorshipMaintenance::class)->remind());
        $this->assertSame(0, app(SponsorshipMaintenance::class)->remind());
        $renewal = $this->create($order->user, ['duration_days' => 6]);
        $this->notify($renewal)->assertOk();
        $this->assertSame(1, app(SponsorshipMaintenance::class)->remind());
        $this->assertSame(3, DB::table('sponsorship_reminders')->count());
    }

    public function test_real_financial_export_and_settings_are_admin_only_and_hide_secrets(): void
    {
        $this->configure();
        $order = $this->create();
        $this->notify($order)->assertOk();
        $legacy = app(SponsorshipService::class)->createOrder(User::factory()->create(), $this->plan());
        $this->actingAs(User::factory()->create(['role' => 'editor', 'status' => 'active']))->get('/admin/payment-report')->assertForbidden();
        $this->get('/admin/sponsorship-orders')->assertForbidden();
        $response = $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']))->get('/admin/payment-report')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($order->order_no, $csv);
        $this->assertStringNotContainsString($legacy->order_no, $csv);
        $this->assertStringNotContainsString('test-channel-secret', $csv);
        $this->get('/admin/settings')->assertOk()->assertDontSee('test-channel-secret');
        $this->get('/admin/sponsorship-orders')->assertOk()->assertSee('真实收款');
    }

    public function test_mock_endpoint_is_disabled_in_production_and_cannot_settle_real_order(): void
    {
        $this->configure();
        $order = $this->create();
        $this->actingAs($order->user)->post('/support/orders/'.$order->id.'/mock-pay')->assertNotFound();
        $legacy = app(SponsorshipService::class)->createOrder($order->user, $this->plan());
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app->detectEnvironment(fn () => 'production');
        $this->post('/support/orders/'.$legacy->id.'/mock-pay')->assertNotFound();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_signature_handles_zero_and_excludes_only_empty_values(): void
    {
        $this->assertSame(md5('a=0&z=1secret'), XunhuGateway::sign(['z' => 1, 'a' => 0, 'empty' => '', 'null' => null, 'hash' => 'ignored'], 'secret'));
        $this->assertSame(901, XunhuGateway::cents('9.01'));
        $this->assertFalse(XunhuGateway::publicHttps('http://example.com'));
        $this->assertFalse(XunhuGateway::publicHttps('https://127.0.0.1/payment'));
    }

    public function test_scheduled_reconciliation_runs_through_database_worker_and_does_not_double_grant(): void
    {
        $this->configure();
        $order = $this->create();
        Http::fake(['https://channel.example/query' => Http::response($this->signed([
            'errcode' => 0, 'appid' => 'channel-app', 'trade_order_id' => $order->order_no,
            'total_fee' => '9.00', 'transaction_id' => 'TX-'.$order->id, 'status' => 'OD',
        ]))]);
        $this->assertSame(1, app(SponsorshipMaintenance::class)->reconcile());
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1])->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertSame('paid', $order->fresh()->status);
        $this->notify($order)->assertOk();
        $this->assertSame(1, $order->user->notifications()->count());
        $this->assertSame(900, $order->user->supporterProfile()->first()->total_amount_cents);
    }

    public function test_query_cannot_close_an_unrelated_order_even_with_valid_signature(): void
    {
        $this->configure();
        $order = $this->create();
        Http::fake(['https://channel.example/query' => Http::response($this->signed([
            'errcode' => 0, 'appid' => 'channel-app', 'trade_order_id' => 'OTHER-ORDER', 'status' => 'CD',
        ]))]);
        app(RealSponsorshipService::class)->query($order);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->payment_issue);
    }

    public function test_editor_cannot_request_refund_and_partial_refund_response_does_not_revoke_rights(): void
    {
        $this->configure();
        $order = $this->create();
        $this->notify($order)->assertOk();
        $editor = User::factory()->create(['role' => 'editor', 'status' => 'active']);
        try {
            app(RealSponsorshipService::class)->refund($order, $editor, '尝试退款');
            $this->fail('编辑员不能退款');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Http::fake(['https://channel.example/refund' => Http::response($this->signed([
            'errcode' => 0, 'trade_order_id' => $order->order_no, 'transaction_id' => 'TX-'.$order->id,
            'refund_fee' => '1.00', 'refund_status' => 'CD',
        ]))]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $result = app(RealSponsorshipService::class)->refund($order, $admin, '用户申请');
        $this->assertSame('paid', $result->status);
        $this->assertSame('unknown', $result->refund_state);
        $this->assertTrue($order->user->fresh()->isSupporter());
    }
}
