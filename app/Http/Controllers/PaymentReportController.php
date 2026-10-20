<?php

namespace App\Http\Controllers;

use App\Models\SponsorshipOrder;
use Illuminate\Http\Request;

class PaymentReportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $from = $data['from'] ?? now()->subDays(30)->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        return response()->streamDownload(function () use ($from, $to): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['订单号', '金额（分）', '状态', '渠道交易号', '支付时间', '退款时间', '退款状态', '待核查事项']);
            SponsorshipOrder::where('channel', 'xunhu')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->orderBy('id')->chunkById(200, function ($orders) use ($stream): void {
                foreach ($orders as $order) {
                    $row = [$order->order_no, $order->amount_cents, $order->statusLabel(), $order->gateway_transaction, $order->paid_at, $order->refunded_at, $order->refund_state, $order->payment_issue];
                    fputcsv($stream, array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : (string) $value, $row));
                }
            });
            fclose($stream);
        }, '真实赞助订单-'.$from.'-'.$to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
