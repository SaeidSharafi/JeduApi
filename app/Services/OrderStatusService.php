<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Admin\Discounts\IncrementDiscountUsageCountsAction;
use App\Actions\Admin\Discounts\ReleasePromotionUsageSlotsAction;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderProvisioningTriggerEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

final class OrderStatusService
{
    public function __construct(
        private ProductReservationService $productReservationService,
        private IncrementDiscountUsageCountsAction $incrementDiscountUsageCounts,
        private ReleasePromotionUsageSlotsAction $releasePromotionUsageSlots,
    ) {}

    /**
     * The primary method called after a payment is confirmed.
     * It cascades all necessary status updates for the entire order.
     * Respects the provisioning trigger configuration.
     */
    public function handlePaymentCompletion(Order $order): void
    {
        // Get provisioning trigger configuration
        $provisioningTrigger = OrderProvisioningTriggerEnum::from(
            config('order.provisioning.trigger', 'any_payment')
        );

        // Check if we should provision based on the trigger
        // @codeCoverageIgnoreStart
        $shouldProvision = match ($provisioningTrigger) {
            OrderProvisioningTriggerEnum::ANY_PAYMENT     => true,
            OrderProvisioningTriggerEnum::FULL_PAYMENT    => $order->fresh()->balance_due <= 0,
            OrderProvisioningTriggerEnum::MANUAL_APPROVAL => false, // Never auto-provision
        };
        // @codeCoverageIgnoreEnd

        if (! $shouldProvision) {
            // Update order status to PROCESSING but don't complete items
            $order->status = OrderStatusEnum::PROCESSING;
            $order->saveQuietly();

            return;
        }

        // Provision: update the status of each individual line item
        foreach ($order->items as $item) {
            $this->completeOrderItemAfterPayment($item);
        }

        // Then, based on the new state of the items, update the parent order's status
        $this->updateParentOrderStatus($order->fresh());
    }

    /**
     * Updates an individual Enrollment based on its parent OrderItem's status.
     * This method can be called from multiple places (payment, refund, cancellation).
     */
    public function updateEnrollmentStatus(OrderItem $item): void
    {
        if (! $item->enrollment) {
            return;
        }

        $newStatus = match ($item->status) {
            // If the item is refunded or cancelled, the student loses access.
            OrderItemStatusEnum::REFUNDED, OrderItemStatusEnum::CANCELLED => EnrollmentStatusEnum::CANCELLED,
            // If the item is completed, the student gets access. Provisioning
            // readiness is tracked separately via provisioning_status, not the
            // enrollment lifecycle status.
            OrderItemStatusEnum::COMPLETED => EnrollmentStatusEnum::ACTIVE,
            // Otherwise, no change.
            default => $item->enrollment->enrollment_status,
        };

        if ($item->enrollment->enrollment_status !== $newStatus) {
            $item->enrollment->enrollment_status = $newStatus;
        }
        if ($newStatus === EnrollmentStatusEnum::ACTIVE && is_null($item->enrollment->access_start_date)) {
            $item->enrollment->access_start_date = now();
        }
        if ($item->enrollment->isDirty()) {
            $item->enrollment->save();
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
            $order->save();

            // Coupon/promotion usage counters reflect COMPLETED orders only, so
            // abandoned or cancelled pending orders never consume the allowance.
            if ($newStatus === OrderStatusEnum::COMPLETED) {
                $this->incrementDiscountUsageCounts->handle($order);
            }

            // Cancelled/failed orders release their coupon/promotion usage slot.
            if ($newStatus    === OrderStatusEnum::CANCELLED
                || $newStatus === OrderStatusEnum::FAILED
            ) {
                $this->releasePromotionUsageSlots->handle($order);
            }

            OrderStatusUpdatedEvent::dispatch($order);
        }
    }

    /**
     * Updates an individual OrderItem and its Enrollment after a payment.
     */
    private function completeOrderItemAfterPayment(OrderItem $item): void
    {
        $newStatus = OrderItemStatusEnum::COMPLETED;

        if ($item->status !== $newStatus) {
            // Payment received → the reserved seat is now occupied by this item.
            $this->productReservationService->consume($item->product_delivery_option_id, $item->qty_ordered);
            $item->status = $newStatus;
            $item->saveQuietly();
        }

        // Create enrollment if it doesn't exist yet (payment completed -> activate access)
        if (! $item->enrollment) {
            $item->enrollment()->firstOrCreate(
                ['order_item_id' => $item->id],
                [
                    'order_id'                   => $item->order_id,
                    'customer_id'                => $item->order->customer_id,
                    'product_delivery_option_id' => $item->product_delivery_option_id,
                    'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
                ]
            );
            $item->load('enrollment');
        }

        $this->updateEnrollmentStatus($item);
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    private function determineOrderStatus(Collection $items): OrderStatusEnum
    {
        if ($items->isEmpty()) {
            return OrderStatusEnum::PENDING;
        }
        $statusMap = [
            'all_refunded'  => OrderStatusEnum::REFUNDED,
            'all_cancelled' => OrderStatusEnum::CANCELLED,
            'any_refunded'  => OrderStatusEnum::PARTIALLY_REFUNDED,
            'all_completed' => OrderStatusEnum::COMPLETED,
            'default'       => OrderStatusEnum::PROCESSING,
        ];
        $statuses = $items->pluck('status');

        if ($statuses->every(fn ($s): bool => $s === OrderItemStatusEnum::REFUNDED)) {
            return $statusMap['all_refunded'];
        }
        if ($statuses->every(fn ($s): bool => $s === OrderItemStatusEnum::CANCELLED)) {
            return $statusMap['all_cancelled'];
        }
        if ($statuses->contains(OrderItemStatusEnum::REFUNDED)) {
            return $statusMap['any_refunded'];
        }
        if ($statuses->every(fn ($s): bool => $s === OrderItemStatusEnum::COMPLETED)) {
            return $statusMap['all_completed'];
        }

        return OrderStatusEnum::PROCESSING;
    }
}
