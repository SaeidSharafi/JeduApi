<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Actions\Admin\Discounts\ReleasePromotionUsageSlotsAction;
use App\Actions\Admin\Discounts\SettleCompletedOrderAction;
use App\Actions\Payment\FailPendingOrderPaymentsAction;
use App\Data\Admin\Order\OrderUpdateData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderCancellationReasonEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Order;
use App\Services\ProductReservationService;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOrderAction
{
    public function __construct(
        private ProductReservationService $productReservationService,
        private ReleasePromotionUsageSlotsAction $releasePromotionUsageSlots,
        private SettleCompletedOrderAction $settleCompletedOrder,
        private FailPendingOrderPaymentsAction $failPendingOrderPayments,
    ) {}

    /**
     * Execute the action.
     */
    public function handle(OrderUpdateData $data, Order $order): Order
    {

        $updatedOrder = DB::transaction(function () use ($data, $order): Order {
            $wasCancelled = $order->status === OrderStatusEnum::CANCELLED;

            $order->update($data->toArray());
            $order->refresh();

            if (! $wasCancelled && $order->status === OrderStatusEnum::CANCELLED) {
                $order->markCancelled(OrderCancellationReasonEnum::ADMIN);
            } elseif ($wasCancelled && $order->status !== OrderStatusEnum::CANCELLED) {
                // Reopening an order clears the stale cancellation metadata.
                $order->cancelled_at     = null;
                $order->cancelled_reason = null;
                $order->save();
            }

            $order->load('enrollments');

            // Cancelled/failed orders release the coupon/promotion slot.
            // Refunded orders keep it — a refund does not restore usage.
            if ($order->status    === OrderStatusEnum::CANCELLED
                || $order->status === OrderStatusEnum::FAILED
            ) {
                $this->releasePromotionUsageSlots->handle($order);
            }

            // An admin flip straight to COMPLETED bypasses OrderStatusService, so
            // settle the usage counters and the order's rewards here. Both steps
            // are internally idempotent.
            if ($order->status === OrderStatusEnum::COMPLETED) {
                $this->settleCompletedOrder->handle($order);
            }

            if ($order->status    === OrderStatusEnum::CANCELLED
                || $order->status === OrderStatusEnum::REFUNDED
            ) {
                $order->load('items');
                foreach ($order->items as $item) {
                    if ($item->status === OrderItemStatusEnum::PENDING) {
                        $this->productReservationService->release($item->product_delivery_option_id, $item->qty_ordered);
                    }
                }
                $order->enrollments->each(function ($enrollment): void {
                    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::CANCELLED]);
                });
            }

            OrderStatusUpdatedEvent::dispatch($order);

            return $order;
        });

        // A closed order cannot expect another payment. Close its open attempts
        // in the same request after the order transaction commits.
        if ($updatedOrder->status->closesPaymentAttempts()) {
            $this->failPendingOrderPayments->handle($updatedOrder->id);
            $updatedOrder->load('payments');
        }

        return $updatedOrder;
    }
}
