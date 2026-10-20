<?php

namespace App\Http\Controllers;

use App\Models\SponsorshipOrder;
use App\Services\RealSponsorshipService;
use Illuminate\Http\Request;
use Throwable;

class XunhuPaymentController extends Controller
{
    public function notify(Request $request, RealSponsorshipService $service)
    {
        try {
            $service->callback($request->post());
        } catch (Throwable) {
            // 不将密钥、完整回调或渠道响应写入异常页面。
            return response('fail', 400)->header('Content-Type', 'text/plain');
        }

        return response('success')->header('Content-Type', 'text/plain');
    }

    public function check(Request $request, SponsorshipOrder $order, RealSponsorshipService $service)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        $service->query($order);

        return redirect()->route('support.result', ['order' => $order->order_no]);
    }
}
