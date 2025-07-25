<?php

namespace App\Listeners;

use App\Enums\DeliveryMethodEnum;
use App\Enums\EnrolmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\OrderItem;

class UpdateOrderAndEnrollmentStatusesListener
{
    /**
     * Handle the event by dispatching tasks to focused methods.
     */
    public function handle(PaymentCompletedEvent $event): void
    {
        $order = $event->payment->order()
            ->with('items', fn($q) => $q->with('enrolment.productDeliveryOption'))
            ->first();

        if (!$order) {
            \Log::warning("PaymentCompletedEvent fired for a payment (ID: {$event->payment->id}) with a missing order.");
            return;
        }

        // Set the primary order status immediately, as per our business rule.
        $this->updateOrderStatus($order);

        // Process each line item to update its specific statuses.
        foreach ($order->items as $item) {
            $this->updateEnrollmentStatus($item);
            $this->updateOrderItemStatus($item, $order->balance_due);
        }
    }

    /**
     * Update the master status of the Order.
     */
    private function updateOrderStatus(Order $order): void
    {
        $order->status = OrderStatusEnum::COMPLETED;
        $order->save();
    }

    /**
     * Update the status of an Enrolment based on its conditions.
     */
    private function updateEnrollmentStatus(OrderItem $item): void
    {
        // Guard clause: do nothing if there's no pending enrollment.
        if (!$item->enrolment || $item->enrolment->enrollment_status !== EnrolmentStatusEnum::PENDING_PROVISIONING) {
            return;
        }

        // Business Rule: Activate IN_PERSON enrollments on any payment.
        if ($item->productDeliveryOption->delivery_method === DeliveryMethodEnum::IN_PERSON) {
            $item->enrolment->enrollment_status = EnrolmentStatusEnum::ACTIVE;
            $item->enrolment->access_start_date = now();
            $item->enrolment->save();
        }
    }

    /**
     * Update the status of the OrderItem itself based on the order's financial state.
     */
    private function updateOrderItemStatus(OrderItem $item, int $balanceDue): void
    {
        // Determine the new status based on financial state.
        $newStatus = $this->determineOrderItemStatus($item, $balanceDue);

        // Update only if the status needs to change.
        if ($item->status !== $newStatus) {
            $item->status = $newStatus;
            $item->save();
        }
    }

    /**
     * Determine the correct status for an OrderItem.
     */
    private function determineOrderItemStatus(OrderItem $item, int $balanceDue): OrderItemStatusEnum
    {
        // Rule 1: If the entire order is fully paid, all items are completed.
        if ($balanceDue <= 0) {
            return OrderItemStatusEnum::COMPLETED;
        }

        // Rule 2: If the order is partially paid, only full-payment items are considered completed.
        if ($item->payment_type === OrderItemPaymentTypeEnum::FULL_PAYMENT) {
            return OrderItemStatusEnum::COMPLETED;
        }

        // Default: Otherwise, the item remains in its current (likely pending) state.
        return $item->status;
    }
}
