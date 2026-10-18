<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Actions\Admin\Discounts\ReleasePromotionUsageSlotsAction;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderCancellationReasonEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Enrollment;
use App\Models\Order;
use App\Services\ProductReservationService;

/**
 * Performs the cancellation transition and its effects on one order.
 *
 * Call it inside the caller's DB transaction, after the caller's own
 * eligibility checks: ownership, admin intent and the abandonment window stay
 * with their entry points (`CancelOrderByCustomerAction`,
 * `UpdateOrderAction`, `orders:cancel-abandoned`).
 *
 * It deliberately does not open a transaction and does not close pending
 * payment attempts: `FailPendingOrderPaymentsAction` must run after the order
 * transaction commits (the order row is locked here, and taking payment locks
 * inside it would invert the `payment -> order` order used by verification),
 * so every caller invokes that cascade itself once the transaction returns.
 *
 * Cancelling is idempotent: an order that is already cancelled is left
 * untouched, so a repeated admin request cannot release a reservation that
 * another order has since taken.
 */
final readonly class CancelOrderAction
{
    public function __construct(
        private ProductReservationService $productReservationService,
        private ReleasePromotionUsageSlotsAction $releasePromotionUsageSlots,
    ) {}

    public function handle(Order $order, OrderCancellationReasonEnum $reason): Order
    {
        if ($order->status === OrderStatusEnum::CANCELLED) {
            return $order;
        }

        $order->markCancelled($reason);

        // A cancelled order no longer holds the coupon/promotion slot.
        $this->releasePromotionUsageSlots->handle($order);

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            // Only unpaid lines still hold a seat; a completed line consumed
            // its reservation when payment landed, and releasing it again would
            // hand the same seat to another order.
            if ($item->status === OrderItemStatusEnum::PENDING) {
                $this->productReservationService->release($item->product_delivery_option_id, $item->qty_ordered);
            }
        }

        // Cancelling revokes any entitlement the order still carries. An unpaid
        // order only ever has AWAITING_PAYMENT enrollments, so the wider filter
        // changes nothing in a valid state and keeps access from leaking if an
        // impossible one ever exists.
        $order->enrollments()
            ->where('enrollment_status', '!=', EnrollmentStatusEnum::CANCELLED)
            ->each(function (Enrollment $enrollment): void {
                $enrollment->enrollment_status = EnrollmentStatusEnum::CANCELLED;
                $enrollment->save();
            });

        OrderStatusUpdatedEvent::dispatch($order->load(['items', 'enrollments']));

        return $order;
    }
}
