<?php

namespace App\Services;

use App\Enums\EnrolmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

class OrderStatusService
{
    /**
     * The primary method called after a payment is confirmed.
     * It cascades all necessary status updates for the entire order.
     */
    public function handlePaymentCompletion(Order $order): void
    {
        // First, update the status of each individual line item.
        foreach ($order->items as $item) {
            $this->completeOrderItemAfterPayment($item);
        }

        // Then, based on the new state of the items, update the parent order's status.
        $this->updateParentOrderStatus($order->fresh());
    }

    /**
     * Updates an individual OrderItem and its Enrolment after a payment.
     */
    private function completeOrderItemAfterPayment(OrderItem $item): void
    {
        // Determine the new status for the item.
        // Rule: An item is COMPLETED if its initial required payment has been made.
        $newStatus = OrderItemStatusEnum::COMPLETED;

        if ($item->status !== $newStatus) {
            $item->status = $newStatus;
            $item->saveQuietly(); // Use saveQuietly to prevent firing observers if you have them.
        }

        // Now, update the enrollment based on the item's new status.
        $this->updateEnrollmentStatus($item);
    }

    /**
     * Updates an individual Enrolment based on its parent OrderItem's status.
     * This method can be called from multiple places (payment, refund, cancellation).
     */
    public function updateEnrollmentStatus(OrderItem $item): void
    {
        if (!$item->enrolment) {
            return;
        }

        $newStatus = match ($item->status) {
            // If the item is refunded or cancelled, the student loses access.
            OrderItemStatusEnum::REFUNDED, OrderItemStatusEnum::CANCELLED => EnrolmentStatusEnum::CANCELLED,
            // If the item is completed, the student gets access.
            OrderItemStatusEnum::COMPLETED => EnrolmentStatusEnum::ACTIVE,
            // Otherwise, no change.
            default => $item->enrolment->enrollment_status,
        };

        if ($item->enrolment->enrollment_status !== $newStatus) {
            $item->enrolment->enrollment_status = $newStatus;
            if ($newStatus === EnrolmentStatusEnum::ACTIVE && is_null($item->enrolment->access_start_date)) {
                $item->enrolment->access_start_date = now();
            }
            $item->enrolment->saveQuietly();
        }
    }

    /**
     * Updates the parent Order's status based on the collective state of its items.
     * This is the single source of truth for the Order's status.
     */
    public function updateParentOrderStatus(Order $order): void
    {
        $newStatus = $this->determineOrderStatus($order->items);

        if ($order->status !== $newStatus) {
            $order->status = $newStatus;
            $order->saveQuietly();
        }
    }

    private function determineOrderStatus(Collection $items): OrderStatusEnum
    {
        if ($items->isEmpty()) {
            return OrderStatusEnum::PENDING;
        }
        $statusMap = [
            'all_refunded' => OrderStatusEnum::REFUNDED,
            'all_cancelled' => OrderStatusEnum::CANCELLED,
            'any_refunded' => OrderStatusEnum::PARTIALLY_REFUNDED,
            'all_completed' => OrderStatusEnum::COMPLETED,
            'default' => OrderStatusEnum::PROCESSING,
        ];
        $statuses = $items->pluck('status');

        if ($statuses->every(fn($s) => $s === OrderItemStatusEnum::REFUNDED)) {
            return $statusMap['all_refunded'];
        }
        if ($statuses->every(fn($s) => $s === OrderItemStatusEnum::CANCELLED)) {
            return $statusMap['all_cancelled'];
        }
        if ($statuses->contains(OrderItemStatusEnum::REFUNDED)) {
            return $statusMap['any_refunded'];
        }
        if ($statuses->every(fn($s) => $s === OrderItemStatusEnum::COMPLETED)) {
            return $statusMap['all_completed'];
        }

        return OrderStatusEnum::PROCESSING;
    }
}
