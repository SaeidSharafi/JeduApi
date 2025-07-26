<?php

namespace App\Listeners;

use App\Events\PaymentCompletedEvent;
use App\Services\OrderStatusService;

readonly class UpdateStatusesAfterPaymentListener
{
    public function __construct(private OrderStatusService $orderStatusService) {}

    public function handle(PaymentCompletedEvent $event): void
    {
        $order = $event->payment->order;
        if ($order) {
            $this->orderStatusService->handlePaymentCompletion($order->fresh());
        }
    }
}
