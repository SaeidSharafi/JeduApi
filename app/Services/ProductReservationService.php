<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\DB;

/**
 * Tracks seat reservations on ProductDeliveryOption rows.
 *
 * reserved_count holds seats for orders that are PENDING (created but not yet paid).
 * The lifecycle:
 *  - reserve():  called when an order is created (items PENDING)
 *  - consume():  called when an item transitions to COMPLETED (payment received)
 *  - release():  called when an order/item is cancelled or the order is deleted
 *
 * enrolled_count (occupying enrollments) is maintained by
 * UpdateProductDeliveryOptionEnrolledCount; reserved_count covers the unpaid window
 * so capacity = enrolled_count + reserved_count + qty never exceeds capacity.
 */
final class ProductReservationService
{
    /**
     * Reserve seats for a pending order item.
     */
    public function reserve(int $deliveryOptionId, int $qty): void
    {
        ProductDeliveryOption::query()
            ->whereKey($deliveryOptionId)
            ->increment('reserved_count', $qty);
    }

    /**
     * Consume a reservation when the item is fully paid (COMPLETED).
     */
    public function consume(int $deliveryOptionId, int $qty): void
    {
        $this->decrement($deliveryOptionId, $qty);
    }

    /**
     * Release a reservation (cancel/fail/refund before payment, order deletion).
     */
    public function release(int $deliveryOptionId, int $qty): void
    {
        $this->decrement($deliveryOptionId, $qty);
    }

    /**
     * Atomically decrement reserved_count without going below zero.
     */
    private function decrement(int $deliveryOptionId, int $qty): void
    {
        ProductDeliveryOption::query()
            ->whereKey($deliveryOptionId)
            ->update([
                'reserved_count' => DB::raw('GREATEST(reserved_count - '.(int) $qty.', 0)'),
            ]);
    }
}
