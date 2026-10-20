<?php

namespace App\Http\Controllers;

use App\Models\SponsorshipOrder;
use App\Models\SponsorshipPlan;
use App\Services\RealSponsorshipService;
use App\Services\SponsorshipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SponsorshipOrderController extends Controller
{
    public function store(Request $request, RealSponsorshipService $service): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:sponsorship_plans,id'],
        ]);

        $plan = SponsorshipPlan::query()->active()->findOrFail((int) $data['plan_id']);
        $order = $service->create($request->user(), $plan);

        return redirect()->route('support.result', ['order' => $order->order_no]);
    }

    public function mockPay(Request $request, SponsorshipOrder $order, SponsorshipService $service): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']) && $order->channel !== 'xunhu', 404);
        abort_unless($order->user_id === $request->user()->id, 403);

        $service->markPaid($order, 'mock', $request->user());

        return redirect()->route('support.result', ['order' => $order->order_no]);
    }
}
