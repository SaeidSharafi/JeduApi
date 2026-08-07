<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\RefundCompletedEvent;
use App\Notifications\Order\RefundCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
final class SendRefundCompletedNotification implements ShouldQueue
{
    use Queueable;

    public function handle(RefundCompletedEvent $event): void
    {
        $refund = $event->refund->loadMissing([
            'orderItem.order.customer',
        ]);

        $refund->orderItem->order->customer->notify(
            new RefundCompletedNotification($refund)
        );
    }
}
