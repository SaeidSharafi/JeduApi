<?php

declare(strict_types=1);

namespace App\Actions\Shop\Order;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class CancelOrderByCustomerAction
{
    /**
     * Cancel an order by the customer.
     *
     * Business rules:
     * - Order must be in PENDING status
     * - Order must not have any COMPLETED payments
     * - Only the customer who owns the order can cancel it
     */
    public function execute(Order $order, int $userId): Order
    {
        // Verify the order belongs to the authenticated customer
        if ($order->customer_id !== $userId) {
            throw new ModelNotFoundException('Order not found');
        }

        // Verify order is in PENDING status
        if ($order->status !== OrderStatusEnum::PENDING) {
            throw new DomainException(
                'Only pending orders can be cancelled. Current status: '.$order->status->value
            );
        }

        // Check if order has any completed payments
        $hasCompletedPayments = $order->payments()
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->exists();

        if ($hasCompletedPayments) {
            throw new DomainException(
                'Cannot cancel an order with completed payments. Please contact support for refund assistance.'
            );
        }

        return DB::transaction(function () use ($order) {
            // Update order status to CANCELLED
            $order->status = OrderStatusEnum::CANCELLED;
            $order->save();

            // Cancel any enrollments (if they exist, though they shouldn't for unpaid orders)
            // Use each() instead of update() to fire model events for enrolled_count tracking
            $order->enrollments()
                ->where('enrollment_status', '!=', EnrollmentStatusEnum::CANCELLED)
                ->each(function ($enrollment) {
                    $enrollment->enrollment_status = EnrollmentStatusEnum::CANCELLED;
                    $enrollment->save();
                });

            // Reload relationships for the response
            $order->load([
                'items',
                'payments',
                'enrollments',
            ]);

            return $order;
        });
    }
}
