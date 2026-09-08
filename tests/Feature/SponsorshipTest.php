<?php

namespace Tests\Feature;

use App\Filament\Resources\PaymentLogs\PaymentLogResource;
use App\Filament\Resources\SponsorshipOrders\SponsorshipOrderResource;
use App\Filament\Resources\SponsorshipPlans\SponsorshipPlanResource;
use App\Models\SponsorshipOrder;
use App\Models\SponsorshipPlan;
use App\Models\SupporterProfile;
use App\Models\User;
use App\Services\SponsorshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SponsorshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_support_page_lists_active_plans_and_navigation_entry(): void
    {
        $active = $this->plan('月度支持', 'monthly-support', 900);
        $inactive = $this->plan('隐藏方案', 'hidden-support', 100, ['is_active' => false]);

        $this->get('/support')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Support/Index')
                ->where('support.plans.0.id', $active->id)
                ->where('support.plans.0.name', '月度支持')
                ->where('support.plans.0.amount_label', '¥9.00')
                ->has('support.plans', 1)
                ->where('support.auth.can_support', false)
                ->where('support.navigation.4.label', '时间线')
                ->where('support.navigation.4.url', '/timeline')
                ->where('support.navigation.6.label', '支持本站')
            );

        $this->assertDatabaseHas('sponsorship_plans', ['id' => $inactive->id, 'is_active' => false]);
    }

    public function test_guest_cannot_create_or_view_private_sponsorship_pages(): void
    {
        $plan = $this->plan();

        $this->post('/support/orders', ['plan_id' => $plan->id])->assertRedirect(route('login'));
        $this->get('/support/result')->assertRedirect(route('login'));
        $this->get('/me/sponsorships')->assertRedirect(route('login'));
    }

    public function test_user_can_create_mock_order_and_complete_supporter_identity(): void
    {
        $user = User::factory()->create(['name' => '支持者用户']);
        $plan = $this->plan('年度支持', 'annual-support', 9900, [
            'duration_days' => 365,
            'badge_level' => 'gold',
        ]);

        $this->actingAs($user)
            ->post('/support/orders', ['plan_id' => $plan->id])
            ->assertRedirect();

        $order = SponsorshipOrder::query()->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertDatabaseHas('payment_logs', [
            'sponsorship_order_id' => $order->id,
            'event_type' => 'order_created',
            'status' => 'received',
        ]);

        $this->actingAs($user)
            ->post('/support/orders/'.$order->id.'/mock-pay')
            ->assertRedirect(route('support.result', ['order' => $order->order_no]));

        $order->refresh();
        $user->refresh();

        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($user->supporter_until);
        $this->assertTrue($user->isSupporter());
        $this->assertDatabaseHas('supporter_profiles', [
            'user_id' => $user->id,
            'display_name' => '支持者用户',
            'badge_level' => 'gold',
            'total_amount_cents' => 9900,
        ]);
        $this->assertDatabaseHas('payment_logs', [
            'sponsorship_order_id' => $order->id,
            'event_type' => 'payment_success',
            'status' => 'processed',
        ]);
        $this->assertSame(1, $user->notifications()->count());

        $this->actingAs($user)
            ->get('/support/result?order='.$order->order_no)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Support/Result')
                ->where('supportResult.order.status', 'paid')
                ->where('supportResult.order.status_label', '已支付')
            );
    }

    public function test_user_sponsorship_center_is_owner_only_and_allows_public_profile_toggle(): void
    {
        $user = User::factory()->create(['name' => '当前支持者']);
        $other = User::factory()->create(['name' => '其他支持者']);
        $plan = $this->plan();
        $service = app(SponsorshipService::class);

        $order = $service->createOrder($user, $plan);
        $service->markPaid($order, 'mock', $user);
        $service->markPaid($service->createOrder($other, $plan), 'mock', $other);

        $this->actingAs($user)
            ->get('/me/sponsorships')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Sponsorships')
                ->where('me.user.name', '当前支持者')
                ->has('me.orders.data', 1)
                ->where('me.orders.data.0.order_no', $order->order_no)
                ->where('me.supporter_profile.show_publicly', true)
            );

        $this->actingAs($user)
            ->patch('/me/sponsorships/profile', [
                'display_name' => '公开昵称',
                'show_publicly' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('supporter_profiles', [
            'user_id' => $user->id,
            'display_name' => '公开昵称',
            'show_publicly' => false,
        ]);
    }

    public function test_supporter_wall_only_shows_public_profiles_without_private_order_fields(): void
    {
        $publicUser = User::factory()->create(['name' => '公开支持者']);
        $privateUser = User::factory()->create(['name' => '私密支持者']);
        $plan = $this->plan();
        $service = app(SponsorshipService::class);

        $publicOrder = $service->createOrder($publicUser, $plan);
        $service->markPaid($publicOrder, 'mock', $publicUser);
        $privateOrder = $service->createOrder($privateUser, $plan);
        $service->markPaid($privateOrder, 'mock', $privateUser);
        SupporterProfile::query()->where('user_id', $privateUser->id)->update(['show_publicly' => false]);

        $this->get('/supporters')
            ->assertOk()
            ->assertDontSee($publicOrder->order_no)
            ->assertDontSee($privateUser->email)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Supporters/Index')
                ->has('supportersPage.supporters.data', 1)
                ->where('supportersPage.supporters.data.0.display_name', '公开支持者')
                ->where('supportersPage.supporters.data.0.total_amount_label', '¥9.00')
            );
    }

    public function test_admin_can_visit_sponsorship_management_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(SponsorshipPlanResource::getUrl())->assertOk();
        $this->actingAs($user)->get(SponsorshipOrderResource::getUrl())->assertOk();
        $this->actingAs($user)->get(PaymentLogResource::getUrl())->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(string $name = '月度支持', string $slug = 'monthly-support', int $amountCents = 900, array $attributes = []): SponsorshipPlan
    {
        return SponsorshipPlan::query()->create(array_merge([
            'name' => $name,
            'slug' => $slug,
            'amount_cents' => $amountCents,
            'duration_days' => 31,
            'badge_level' => 'supporter',
            'benefits' => "测试权益一\n测试权益二",
            'is_active' => true,
            'sort_order' => 10,
        ], $attributes));
    }
}
