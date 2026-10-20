<?php

namespace App\Jobs;

use App\Models\SponsorshipOrder;
use App\Services\RealSponsorshipService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileSponsorshipOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $orderId) {}

    public function handle(RealSponsorshipService $service): void
    {
        $order = SponsorshipOrder::find($this->orderId);
        if ($order && $order->channel === 'xunhu' && $order->status === 'pending') {
            $service->query($order);
        }
    }
}
