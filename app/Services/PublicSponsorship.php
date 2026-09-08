<?php

namespace App\Services;

use App\Models\SponsorshipOrder;
use App\Models\SponsorshipPlan;
use App\Models\SupporterProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublicSponsorship
{
    public function __construct(private readonly PublicHomepage $homepage, private readonly PublicSeo $seo) {}

    /**
     * @return array<string, mixed>
     */
    public function supportPage(?User $user): array
    {
        return [
            ...$this->homepage->shell(),
            'seo' => $this->seo->support(),
            'plans' => SponsorshipPlan::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('amount_cents')
                ->get()
                ->map(fn (SponsorshipPlan $plan): array => $this->planItem($plan))
                ->all(),
            'auth' => [
                'can_support' => $user instanceof User,
                'is_supporter' => $user?->isSupporter() ?? false,
                'supporter_until' => $user?->supporter_until?->format('Y-m-d H:i'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resultPage(User $user, ?string $orderNo): array
    {
        $query = $user->sponsorshipOrders()->with('plan')->latest();
        $order = filled($orderNo)
            ? (clone $query)->where('order_no', $orderNo)->first()
            : $query->first();

        return [
            ...$this->homepage->shell(),
            'order' => $order instanceof SponsorshipOrder ? $this->orderItem($order) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function supporterWall(): array
    {
        $profiles = SupporterProfile::query()
            ->public()
            ->where('total_amount_cents', '>', 0)
            ->with('user')
            ->orderByDesc('last_supported_at')
            ->orderByDesc('id')
            ->paginate(24);

        $totalAmount = SupporterProfile::query()->sum('total_amount_cents');

        return [
            ...$this->homepage->shell(),
            'seo' => $this->seo->supporters(),
            'summary' => [
                'total_supporters' => SupporterProfile::query()->where('total_amount_cents', '>', 0)->count(),
                'total_amount_label' => $this->moneyLabel((int) $totalAmount),
            ],
            'supporters' => $this->paginate($profiles, fn (SupporterProfile $profile): array => $this->supporterItem($profile)),
        ];
    }

    /**
     * @param  array<string, mixed>  $shell
     * @return array<string, mixed>
     */
    public function userCenter(User $user, array $shell): array
    {
        $user->loadMissing('supporterProfile');
        $orders = $user->sponsorshipOrders()
            ->with('plan')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return [
            ...$shell,
            'supporter_profile' => $user->supporterProfile instanceof SupporterProfile
                ? $this->supporterProfileItem($user->supporterProfile)
                : null,
            'orders' => $this->paginate($orders, fn (SponsorshipOrder $order): array => $this->orderItem($order)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function planItem(SponsorshipPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'amount_cents' => $plan->amount_cents,
            'amount_label' => $plan->amountLabel(),
            'duration_days' => $plan->duration_days,
            'duration_label' => $plan->duration_days ? $plan->duration_days.' 天支持者身份' : '一次性支持',
            'badge_level' => $plan->badge_level,
            'badge_label' => $plan->badgeLabel(),
            'benefits' => collect(preg_split('/\r\n|\r|\n/', (string) $plan->benefits))
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderItem(SponsorshipOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'plan_name' => $order->plan?->name ?? '已删除方案',
            'amount_label' => $order->amountLabel(),
            'channel' => $order->channel,
            'channel_label' => $order->channelLabel(),
            'status' => $order->status,
            'status_label' => $order->statusLabel(),
            'can_pay' => $order->canBePaid(),
            'created_at' => $order->created_at?->format('Y-m-d H:i'),
            'paid_at' => $order->paid_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function supporterProfileItem(SupporterProfile $profile): array
    {
        return [
            'display_name' => $profile->display_name,
            'resolved_display_name' => $profile->displayName(),
            'show_publicly' => $profile->show_publicly,
            'badge_level' => $profile->badge_level,
            'badge_label' => $profile->badgeLabel(),
            'total_amount_label' => $this->moneyLabel($profile->total_amount_cents),
            'last_supported_at' => $profile->last_supported_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supporterItem(SupporterProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'display_name' => $profile->displayName(),
            'badge_label' => $profile->badgeLabel(),
            'total_amount_label' => $this->moneyLabel($profile->total_amount_cents),
            'last_supported_at' => $profile->last_supported_at?->format('Y-m-d'),
        ];
    }

    /**
     * @template TModel
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  callable(TModel): array<string, mixed>  $mapper
     * @return array<string, mixed>
     */
    private function paginate(LengthAwarePaginator $paginator, callable $mapper): array
    {
        return [
            'data' => collect($paginator->items())->map($mapper)->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }

    private function moneyLabel(int $amountCents): string
    {
        return '¥'.number_format($amountCents / 100, 2);
    }
}
